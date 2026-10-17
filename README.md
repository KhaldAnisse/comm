# ShopCDC : e-commerce Laravel + Debezium + Kafka + MinIO (couche bronze)

Ce projet est une boutique en ligne écrite avec Laravel. Chaque **ajout au panier** et chaque **mise en favori** est capturé
par **Debezium** (Change Data Capture), publié dans **Kafka**, puis stocké tel quel dans la
**couche bronze** d'un data lake **MinIO**.

```
┌──────────────┐  SQL   ┌──────────┐ binlog ┌───────────────────────── Kafka Connect ─────────────────────────┐
│ Laravel app  │ ─────▶ │  MySQL   │ ─────▶ │ Debezium MySQL Source ──▶ Kafka topics ──▶ S3 Sink Connector     │
│ (panier,     │        │ (ROW     │        │                      shop.ecommerce.cart_items                  │
│  favoris)    │        │  binlog) │        │                      shop.ecommerce.wishlists                   │
└──────────────┘        └──────────┘        └──────────────────────────────────────────────┬──────────────────┘
                                                                                           ▼
                                                     MinIO  s3://bronze/ecommerce/<topic>/year=/month=/day=/hour=/*.json
                                                            s3://silver/…  s3://gold/…   (étapes suivantes)
```

L'application **n'écrit jamais dans Kafka**. Elle fait des `INSERT`, `UPDATE` et `DELETE` classiques. Debezium lit le journal
binaire (binlog) de MySQL et transforme chaque modification en événement. C'est tout l'intérêt du CDC : on n'a pas besoin
de toucher au code métier et on ne perd aucun événement, même si Kafka est indisponible un moment.

---

## Structure du dépôt

```
.
├── docker-compose.yml                 # MySQL, Laravel, Kafka (KRaft), Kafka Connect, MinIO, Kafka UI
├── mysql/init.sql                     # Utilisateur MySQL dédié à Debezium
├── connectors/
│   ├── debezium-mysql-source.json     # MySQL  -> Kafka
│   ├── minio-s3-sink.json             # Kafka  -> MinIO (bucket bronze)
│   └── register.sh                    # Déploie les 2 connecteurs via l'API REST de Kafka Connect
└── laravel-app/
    ├── Dockerfile                     # composer create-project laravel/laravel + code de src/
    ├── docker-entrypoint.sh           # migrate --seed puis artisan serve
    └── src/
        ├── app/Models/                # Product, CartItem, Wishlist, User
        ├── app/Http/Controllers/      # ProductController, CartController, WishlistController, AuthController
        ├── database/migrations/       # products, cart_items, wishlists
        ├── database/seeders/          # 2 utilisateurs + 9 produits
        ├── resources/views/           # Vues Blade (catalogue, panier, favoris, connexion)
        └── routes/web.php
```

---

## Partie 1 : l'application Laravel

### Modèle de données

| Table        | Rôle                        | Événement métier → opération SQL → `op` Debezium                                   |
|--------------|-----------------------------|-------------------------------------------------------------------------------------|
| `products`   | Catalogue                   | non capturée (sert de dimension plus tard)                                          |
| `cart_items` | Lignes de panier            | ajout → `INSERT` → `c` · changement de quantité → `UPDATE` → `u` · retrait → `DELETE` → `d` |
| `wishlists`  | Favoris                     | mise en favori → `INSERT` → `c` · retrait → `DELETE` → `d`                          |

### Points clés du code

- `CartController@store` (`laravel-app/src/app/Http/Controllers/CartController.php`) fait un `firstOrNew` sur
  `(user_id, product_id)`. Le premier ajout produit un `INSERT`. Les ajouts suivants du même produit produisent un
  `UPDATE` de `quantity`. L'image `before`/`after` de Debezium permet alors de calculer la quantité ajoutée.
- `WishlistController@toggle` crée ou supprime la ligne de favori.
- Les routes panier et favoris sont protégées par le middleware `auth`, pour qu'un `user_id` soit attaché à chaque événement.

### Lancer l'application seule (sans Docker)

```bash
composer create-project laravel/laravel:^11.0 shop
cp -r laravel-app/src/* shop/
cd shop
# Dans .env : DB_CONNECTION=mysql, DB_DATABASE=ecommerce, DB_USERNAME=..., DB_PASSWORD=...
php artisan migrate --seed
php artisan serve
```

---

## Partie 2 : la démarche de réalisation du pipeline CDC

### Étape 0 : prérequis

- Docker et Docker Compose v2, avec environ 6 Go de RAM libres
- `curl`, et éventuellement `jq`

### Étape 1 : préparer MySQL pour le CDC

Debezium lit le **binlog**. Il faut donc :

1. Activer le binlog au format `ROW` avec une image `FULL`, pour avoir l'état complet de la ligne avant et après la
   modification. GTID est recommandé. Ces options sont dans `docker-compose.yml` → service `mysql` → `command`.
2. Créer un utilisateur dédié avec les droits de réplication (`mysql/init.sql`) :
   `SELECT, RELOAD, SHOW DATABASES, REPLICATION SLAVE, REPLICATION CLIENT, LOCK TABLES`.

Vérification :

```sql
SHOW VARIABLES LIKE 'binlog_format';     -- ROW
SHOW VARIABLES LIKE 'binlog_row_image';  -- FULL
```

### Étape 2 : démarrer l'infrastructure

```bash
docker compose up -d --build
docker compose ps
```

| Service        | URL                                   |
|----------------|---------------------------------------|
| Boutique       | http://localhost:8000                 |
| Kafka Connect  | http://localhost:8083                 |
| Kafka UI       | http://localhost:8085                 |
| Console MinIO  | http://localhost:9001 (minioadmin / minioadmin) |

Le service `minio-init` crée les buckets `bronze`, `silver` et `gold`.
Au démarrage, le conteneur `connect` installe deux plugins depuis Confluent Hub : `debezium-connector-mysql` et
`kafka-connect-s3`. Cela prend environ une minute.

```bash
curl -s localhost:8083/connector-plugins | jq '.[].class'
# "io.confluent.connect.s3.S3SinkConnector"
# "io.debezium.connector.mysql.MySqlConnector"
```

### Étape 3 : déployer le connecteur source Debezium (MySQL vers Kafka)

Paramètres importants de `connectors/debezium-mysql-source.json` :

| Paramètre                         | Valeur                                      | Pourquoi                                                    |
|-----------------------------------|---------------------------------------------|-------------------------------------------------------------|
| `topic.prefix`                    | `shop`                                      | Topics nommés `shop.<db>.<table>`                           |
| `table.include.list`              | `ecommerce.cart_items,ecommerce.wishlists`  | Ne capturer que le panier et les favoris                    |
| `snapshot.mode`                   | `initial`                                   | Exporte d'abord l'existant (`op = r`), puis suit le binlog  |
| `decimal.handling.mode`           | `string`                                    | `unit_price` lisible au lieu d'un base64                    |
| `tombstones.on.delete`            | `false`                                     | Pas de message `null` inutile en bronze                     |
| `schema.history.internal.kafka.*` |                                             | Historique des DDL, indispensable au connecteur MySQL       |

### Étape 4 : déployer le connecteur sink S3 (Kafka vers MinIO bronze)

MinIO parle le protocole S3. On utilise donc le connecteur **Confluent S3 Sink** en le pointant sur MinIO :

| Paramètre                      | Valeur                                       |
|--------------------------------|----------------------------------------------|
| `store.url`                    | `http://minio:9000` (endpoint MinIO)          |
| `s3.path.style.access.enabled` | `true` (obligatoire pour MinIO)               |
| `s3.bucket.name` / `topics.dir`| `bronze` / `ecommerce`                        |
| `format.class`                 | `JsonFormat`, l'événement brut                |
| `partitioner.class`            | `TimeBasedPartitioner`, partitions `year=/month=/day=/hour=` |
| `flush.size` / `rotate.schedule.interval.ms` | 50 messages ou 60 s, selon ce qui arrive en premier |

Les identifiants S3 sont passés au worker Connect par les variables `AWS_ACCESS_KEY_ID` et `AWS_SECRET_ACCESS_KEY`.

Déploiement des deux connecteurs :

```bash
./connectors/register.sh
# Les deux statuts doivent être "RUNNING"
```

### Étape 5 : générer des événements

1. Ouvrir http://localhost:8000 et se connecter avec `alice@shop.test` / `password`.
2. Cliquer **Ajouter au panier** sur plusieurs produits, plusieurs fois sur le même produit, puis changer une quantité et
   retirer un produit.
3. Cliquer **♡** pour mettre des produits en favori, puis en retirer un.

### Étape 6 : vérifier dans Kafka

```bash
docker compose exec kafka kafka-topics --bootstrap-server localhost:9092 --list | grep shop

docker compose exec kafka kafka-console-consumer \
  --bootstrap-server localhost:9092 \
  --topic shop.ecommerce.cart_items --from-beginning --property print.key=true
```

Exemple d'événement après un deuxième clic sur « Ajouter au panier » (`UPDATE`) :

```json
{
  "before": { "id": 3, "user_id": 1, "product_id": 5, "quantity": 1, "unit_price": "249.00",
              "created_at": "2026-10-17T10:02:11Z", "updated_at": "2026-10-17T10:02:11Z" },
  "after":  { "id": 3, "user_id": 1, "product_id": 5, "quantity": 2, "unit_price": "249.00",
              "created_at": "2026-10-17T10:02:11Z", "updated_at": "2026-10-17T10:03:40Z" },
  "source": { "connector": "mysql", "name": "shop", "db": "ecommerce", "table": "cart_items",
              "file": "mysql-bin.000003", "pos": 48213, "gtid": "…", "ts_ms": 1760695420000 },
  "op": "u",
  "ts_ms": 1760695420123
}
```

> `op` : `r` = lecture du snapshot, `c` = création, `u` = mise à jour, `d` = suppression (dans ce cas `after` vaut `null`
> et `before` contient la ligne supprimée).

Vous pouvez aussi regarder les topics dans Kafka UI : http://localhost:8085.

### Étape 7 : vérifier la couche bronze dans MinIO

Après au plus 60 secondes :

```bash
docker compose run --rm --entrypoint sh minio-init -c \
  "mc alias set local http://minio:9000 minioadmin minioadmin >/dev/null && mc ls -r local/bronze"
```

```
bronze/ecommerce/shop.ecommerce.cart_items/year=2026/month=10/day=17/hour=10/shop.ecommerce.cart_items+0+0000000000.json
bronze/ecommerce/shop.ecommerce.wishlists/year=2026/month=10/day=17/hour=10/shop.ecommerce.wishlists+0+0000000000.json
```

Chaque fichier contient un événement Debezium JSON par ligne (JSON Lines), **sans transformation**.

---

## Bonnes pratiques pour la couche bronze

1. **Données brutes et immuables.** On garde l'enveloppe Debezium complète (`before`, `after`, `op`, `source`, `ts_ms`).
   On ne fait pas d'`ExtractNewRecordState` à ce niveau : on perdrait l'information de suppression et l'état précédent.
2. **Ajout seul (append-only).** On ne réécrit ni ne supprime jamais les fichiers. Les corrections se font en silver.
3. **Partitionnement temporel** (`year/month/day/hour`). Il facilite les relectures incrémentales et la rétention.
   Une règle de cycle de vie MinIO (`mc ilm rule add`) peut archiver ou purger les anciennes partitions.
4. **Traçabilité.** `source.file`, `source.pos` et `source.gtid` sont conservés avec la partition et l'offset Kafka
   (visibles dans le nom du fichier). On peut ainsi rejouer ou dédupliquer.
5. **Garantie at-least-once.** Debezium et Kafka Connect peuvent livrer des doublons après un redémarrage. La
   déduplication se fait en silver.
6. **En production** : Avro ou Parquet avec un Schema Registry, `replication.factor = 3`, secrets via un
   `ConfigProvider` plutôt qu'en clair, et un utilisateur MinIO dédié avec une politique limitée au bucket `bronze`.

---

## Suite : couches silver et gold

### Silver : nettoyage et dédoublonnage (exemple PySpark)

```python
from pyspark.sql import SparkSession, functions as F

spark = (SparkSession.builder
    .config("spark.hadoop.fs.s3a.endpoint", "http://localhost:9000")
    .config("spark.hadoop.fs.s3a.access.key", "minioadmin")
    .config("spark.hadoop.fs.s3a.secret.key", "minioadmin")
    .config("spark.hadoop.fs.s3a.path.style.access", "true")
    .getOrCreate())

raw = spark.read.json("s3a://bronze/ecommerce/shop.ecommerce.cart_items/")

cart_events = (raw
    .withColumn("row", F.coalesce("after", "before"))
    .select(
        F.col("row.id").alias("cart_item_id"),
        F.col("row.user_id").alias("user_id"),
        F.col("row.product_id").alias("product_id"),
        F.col("op"),
        F.when(F.col("op").isin("c", "r"), F.col("after.quantity"))
         .when(F.col("op") == "u", F.col("after.quantity") - F.col("before.quantity"))
         .when(F.col("op") == "d", -F.col("before.quantity"))
         .alias("quantity_delta"),
        (F.col("ts_ms") / 1000).cast("timestamp").alias("event_time"),
        F.col("source.gtid").alias("gtid"),
    )
    .dropDuplicates(["gtid", "cart_item_id", "op"]))   # idempotence at-least-once

cart_events.write.mode("overwrite").partitionBy("op").parquet("s3a://silver/cart_events/")
```

Il faut ajouter le connecteur S3A au lancement de Spark, par exemple avec
`--packages org.apache.hadoop:hadoop-aws:3.3.4`. Pour des tables transactionnelles, on peut écrire en **Delta Lake** ou
**Apache Iceberg** plutôt qu'en Parquet simple.

### Gold : indicateurs métier

- Taux d'abandon de panier : produits ajoutés au panier et jamais commandés (après jointure avec une future table `orders`).
- Top produits mis en favori par jour ou par catégorie (jointure avec `products`, que l'on peut ajouter à
  `table.include.list`).
- Conversion favori → panier : délai entre la mise en favori et l'ajout au panier d'un même produit.

---

## Dépannage

| Symptôme                                         | Piste                                                                                  |
|--------------------------------------------------|----------------------------------------------------------------------------------------|
| `register.sh` attend indéfiniment                 | `docker compose logs connect`. L'installation des plugins Confluent Hub nécessite Internet. |
| Connecteur source `FAILED` : *Access denied*      | `mysql/init.sql` ne s'exécute qu'au **premier** démarrage. Faites `docker compose down -v` puis relancez. |
| Aucun fichier dans MinIO                          | Attendez `rotate.schedule.interval.ms` (60 s). Vérifiez `curl localhost:8083/connectors/minio-s3-sink/status`. |
| Connecteur S3 : *bucket does not exist*           | Vérifiez que `minio-init` s'est terminé : `docker compose logs minio-init`.             |
| Redéployer après modification d'un JSON           | Relancez `./connectors/register.sh` (PUT idempotent).                                  |
| Tout réinitialiser                                | `docker compose down -v`                                                               |
