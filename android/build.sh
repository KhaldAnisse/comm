#!/usr/bin/env bash
# Construit Tetris.apk à partir des fichiers web à la racine du dépôt.
# Outils requis (Ubuntu/Debian) :
#   apt install aapt apksigner zipalign dalvik-exchange android-sdk-platform-23 default-jdk
set -euo pipefail

cd "$(dirname "$0")"
ANDROID_JAR=${ANDROID_JAR:-/usr/lib/android-sdk/platforms/android-23/android.jar}
BUILD=build
KEYSTORE=${KEYSTORE:-tetris.keystore}

rm -rf "$BUILD"
mkdir -p "$BUILD/gen" "$BUILD/classes" "$BUILD/assets"
cp ../index.html ../style.css ../tetris.js "$BUILD/assets/"

aapt package -f -m -J "$BUILD/gen" -M AndroidManifest.xml -S res -I "$ANDROID_JAR"

javac --release 8 -nowarn -classpath "$ANDROID_JAR" -d "$BUILD/classes" \
  $(find src "$BUILD/gen" -name '*.java')

"${DX:-dalvik-exchange}" --dex --output="$BUILD/classes.dex" "$BUILD/classes"

aapt package -f -M AndroidManifest.xml -S res -A "$BUILD/assets" -I "$ANDROID_JAR" \
  -F "$BUILD/unsigned.apk"
(cd "$BUILD" && aapt add unsigned.apk classes.dex >/dev/null)

zipalign -f 4 "$BUILD/unsigned.apk" "$BUILD/aligned.apk"

if [ ! -f "$KEYSTORE" ]; then
  keytool -genkeypair -keystore "$KEYSTORE" -alias tetris -keyalg RSA -keysize 2048 \
    -validity 10000 -storepass tetris123 -keypass tetris123 -dname "CN=Tetris"
fi

apksigner sign --ks "$KEYSTORE" --ks-pass pass:tetris123 --key-pass pass:tetris123 \
  --out Tetris.apk "$BUILD/aligned.apk"
apksigner verify Tetris.apk
echo "APK créé : $(pwd)/Tetris.apk"
