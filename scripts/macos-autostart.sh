#!/usr/bin/env bash
#
# Biocontrol jarayonlarini macOS launchd orqali doimiy ishlatish: yiqilsa qayta yonadi,
# kompyuter qayta yoqilib, foydalanuvchi kirganda o‘zi ishga tushadi.
#
#   bash scripts/macos-autostart.sh install [ngrok-url]   — o‘rnatish (yoki yangilash)
#   bash scripts/macos-autostart.sh status                — holat
#   bash scripts/macos-autostart.sh uninstall             — o‘chirish
#
# ngrok-url — doimiy domen (ngrok dashboard → Domains), masalan https://xxx.ngrok-free.dev.
# Berilmasa NGROK_URL muhit o‘zgaruvchisi, u ham bo‘lmasa — ngrok tasodifiy manzil oladi.
#
# Ishga tushadiganlar: artisan serve (0.0.0.0:8000, 8 worker), schedule:work, queue:work, ngrok
# va caffeinate (Mac uxlab qolmasin — busiz ekranlar ham, turniket ham to‘xtaydi).
# Loglar: storage/logs/autostart-*.log. Terminalda `composer dev` / `npm run dev` ni bunga qo‘shib ishlatmang.

set -euo pipefail

PROJECT="$(cd "$(dirname "$0")/.." && pwd)"
AGENTS="$HOME/Library/LaunchAgents"
PREFIX="uz.biocontrol"
DOMAIN="gui/$(id -u)"
PORT=8000
NAMES=(serve schedule queue ngrok caffeinate)

PHP="$(command -v php || true)"
NGROK="$(command -v ngrok || true)"
PATH_VALUE="$(dirname "${PHP:-/usr/bin/php}"):/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin"

program_args() {
    local args=()
    case "$1" in
        serve) args=("$PHP" artisan serve --host=0.0.0.0 "--port=$PORT" --no-reload) ;;
        schedule) args=("$PHP" artisan schedule:work) ;;
        queue) args=("$PHP" artisan queue:work --tries=1 --sleep=3) ;;
        ngrok)
            args=("$NGROK" http "$PORT" --log=stdout --log-format=logfmt)
            if [ -n "${NGROK_URL:-}" ]; then
                args+=("--url=$NGROK_URL")
            fi
            ;;
        caffeinate) args=(/usr/bin/caffeinate -ims) ;;
    esac

    for arg in "${args[@]}"; do
        printf '        <string>%s</string>\n' "$arg"
    done
}

write_plist() {
    local name="$1"
    cat > "$AGENTS/$PREFIX.$name.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$PREFIX.$name</string>
    <key>ProgramArguments</key>
    <array>
$(program_args "$name")
    </array>
    <key>WorkingDirectory</key>
    <string>$PROJECT</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>PATH</key>
        <string>$PATH_VALUE</string>
        <key>PHP_CLI_SERVER_WORKERS</key>
        <string>8</string>
    </dict>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>ThrottleInterval</key>
    <integer>5</integer>
    <key>ProcessType</key>
    <string>Interactive</string>
    <key>StandardOutPath</key>
    <string>$PROJECT/storage/logs/autostart-$name.log</string>
    <key>StandardErrorPath</key>
    <string>$PROJECT/storage/logs/autostart-$name.log</string>
</dict>
</plist>
PLIST
}

unload() {
    launchctl bootout "$DOMAIN/$PREFIX.$1" 2>/dev/null || true
}

install() {
    if [ -z "$PHP" ] || [ -z "$NGROK" ]; then
        echo "php yoki ngrok topilmadi (PATH)." >&2
        exit 1
    fi

    NGROK_URL="${1:-${NGROK_URL:-}}"
    mkdir -p "$AGENTS" "$PROJECT/storage/logs"
    rm -f "$PROJECT/public/hot"

    for name in "${NAMES[@]}"; do
        unload "$name"
        write_plist "$name"
        launchctl bootstrap "$DOMAIN" "$AGENTS/$PREFIX.$name.plist"
    done

    echo "O‘rnatildi. Holat: bash scripts/macos-autostart.sh status"
    if [ -n "$NGROK_URL" ]; then
        echo "ngrok: $NGROK_URL"
    fi
}

uninstall() {
    for name in "${NAMES[@]}"; do
        unload "$name"
        rm -f "$AGENTS/$PREFIX.$name.plist"
    done
    echo "O‘chirildi."
}

status() {
    for name in "${NAMES[@]}"; do
        local line pid
        line="$(launchctl list | awk -v l="$PREFIX.$name" '$3 == l')"
        pid="$(echo "$line" | awk '{print $1}')"
        if [ -z "$line" ]; then
            printf '%-11s o‘rnatilmagan\n' "$name"
        elif [ "$pid" = "-" ]; then
            printf '%-11s ishlamayapti (oxirgi chiqish kodi %s) — storage/logs/autostart-%s.log\n' "$name" "$(echo "$line" | awk '{print $2}')" "$name"
        else
            printf '%-11s ishlayapti (pid %s)\n' "$name" "$pid"
        fi
    done

    local url
    url="$(curl -s -m 2 http://127.0.0.1:4040/api/tunnels | grep -oE '"public_url":"[^"]+"' | head -1 | cut -d'"' -f4 || true)"
    echo "ngrok manzili: ${url:-yo‘q}"
}

case "${1:-}" in
    install) install "${2:-}" ;;
    uninstall) uninstall ;;
    status) status ;;
    *) sed -n '3,15p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
