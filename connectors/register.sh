#!/usr/bin/env bash
# Enregistre (ou met à jour) les connecteurs Kafka Connect.
set -euo pipefail

CONNECT_URL="${CONNECT_URL:-http://localhost:8083}"
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "Attente de Kafka Connect sur $CONNECT_URL ..."
until curl -sf "$CONNECT_URL/connectors" > /dev/null; do
  sleep 5
done

for name in debezium-mysql-source minio-s3-sink; do
  echo "-> Déploiement du connecteur $name"
  curl -sf -X PUT \
    -H "Content-Type: application/json" \
    --data @"$DIR/$name.json" \
    "$CONNECT_URL/connectors/$name/config"
  echo
done

sleep 5
for name in debezium-mysql-source minio-s3-sink; do
  curl -s "$CONNECT_URL/connectors/$name/status"
  echo
done
