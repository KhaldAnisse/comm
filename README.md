# Tetris

Clone de Tetris en HTML/CSS/JavaScript, sans dépendance.

## Lancer le jeu

Ouvre `index.html` dans un navigateur (double-clic suffit).

## Commandes

| Touche | Action |
| --- | --- |
| ← → | Déplacer |
| ↑ / X | Rotation horaire |
| Z | Rotation anti-horaire |
| ↓ | Descente rapide |
| Espace | Chute directe |
| C / Shift | Mettre en réserve |
| P / Échap | Pause |
| Entrée | Nouvelle partie |

## Fonctionnalités

- 7 pièces classiques avec tirage « 7-bag »
- Pièce fantôme, aperçu de la suivante, réserve
- Score, lignes et niveau (la vitesse augmente toutes les 10 lignes)

## Application Android (APK)

Le dossier `android/` emballe le jeu dans une application Android (WebView plein écran,
boutons tactiles, pause automatique quand l'appli passe en arrière-plan).

```bash
sudo apt install aapt apksigner zipalign dalvik-exchange android-sdk-platform-23 default-jdk
android/build.sh   # produit android/Tetris.apk
```

Pour l'installer : copie `Tetris.apk` sur le téléphone, ouvre-le et autorise
l'installation d'applications de sources inconnues.
