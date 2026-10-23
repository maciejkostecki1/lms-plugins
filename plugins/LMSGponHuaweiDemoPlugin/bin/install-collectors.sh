#!/bin/bash
# Install the four read-only collectors using a dedicated OS account.
set -euo pipefail
test "$(id -u)" -eq 0 || { echo 'Uruchom instalator jako root.' >&2; exit 1; }
plugin=/var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin
test -d "$plugin/bin"
command -v setfacl >/dev/null
command -v runuser >/dev/null
stamp=$(date +%Y%m%d-%H%M%S)
backup=/root/lms-huawei-demo-collector-runtime-$stamp
mkdir -m 700 "$backup"
tar -czf "$backup/plugin.tar.gz" -C "$(dirname "$plugin")" "$(basename "$plugin")"
if test -f /etc/cron.d/lms; then cp -a /etc/cron.d/lms "$backup/lms.cron"; fi
getfacl -p /etc/lms/lms.ini > "$backup/config.acl"
for path in /etc/cron.d/lms-huawei-demo-collectors /etc/logrotate.d/lms-huawei-demo-collectors; do
    if test -f "$path"; then cp -a "$path" "$backup/$(basename "$path").previous"; fi
done
if ! id lms-huawei-demo >/dev/null 2>&1; then
    useradd --system --user-group --no-create-home --home-dir /var/lib/lms-huawei-demo-collector --shell /sbin/nologin lms-huawei-demo
fi
test "$(id -u lms-huawei-demo)" -ne 0
setfacl -m u:lms-huawei-demo:r-- /etc/lms/lms.ini
install -d -o lms-huawei-demo -g lms-huawei-demo -m 0750 /var/lib/lms-huawei-demo-collector /var/log/lms-huawei-demo
# When the full version exists, serialize network polling with its collector.
if test -f /etc/cron.d/lms-huawei-collectors && test -f /var/lib/lms-huawei-collector/collector.lock; then
    setfacl -m u:lms-huawei-demo:--x /var/lib/lms-huawei-collector
    setfacl -m u:lms-huawei-demo:rw- /var/lib/lms-huawei-collector/collector.lock
    ln -sfn /var/lib/lms-huawei-collector/collector.lock /var/lib/lms-huawei-demo-collector/collector.lock
fi
for name in status diagnostics onts inventory; do
    touch "/var/log/lms-huawei-demo/$name.log"
    chown lms-huawei-demo:lms-huawei-demo "/var/log/lms-huawei-demo/$name.log"
    chmod 0640 "/var/log/lms-huawei-demo/$name.log"
done
# Root owns code and parent directories already prevent replacement by other users.
chown -R root:root "$plugin"
find "$plugin" -type d -exec chmod 0755 {} +
find "$plugin" -type f -exec chmod 0644 {} +
chmod 0700 "$plugin/bin/install-collectors.sh"
runuser -u lms-huawei-demo -- /usr/bin/php -r 'require "/var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/huawei-common.php"; [$pdo,$configs]=hdConnect(); echo "Runtime OK; active OLT: ".count($configs).PHP_EOL;'
# Hold the old collector lock while migrating the schedule, so no old/new job overlaps.
exec 9>/var/lock/lms-huawei-demo-gpon-demo.lock
flock -w 30 9 || { echo 'Trwa kolektor. Ponów instalację po jego zakończeniu.' >&2; exit 1; }
python3 - <<'PY'
from pathlib import Path
import os
p=Path('/etc/cron.d/lms')
if not p.exists():
    raise SystemExit(0)
text=p.read_text()
scripts=['collect-status.php','collect-diagnostics.php','collect-onts.php','collect-inventory.php']
lines=[line for line in text.splitlines() if not ('LMSGponHuaweiDemoPlugin/bin/' in line and any('/'+script in line for script in scripts))]
tmp=p.with_name('lms.hg-tmp')
tmp.write_text('\n'.join(lines)+'\n');os.chmod(str(tmp),0o644);os.chown(str(tmp),0,0);os.replace(str(tmp),str(p))
PY
cron_tmp=$(mktemp)
cat > "$cron_tmp" <<'CRON'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin
# Huawei GPON: read-only SNMP, one shared lock for all four collectors.
2-59/5 * * * * lms-huawei-demo /usr/bin/flock -n /var/lib/lms-huawei-demo-collector/collector.lock /usr/bin/php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/collect-status.php >> /var/log/lms-huawei-demo/status.log 2>&1
17 * * * * lms-huawei-demo /usr/bin/flock -n /var/lib/lms-huawei-demo-collector/collector.lock /usr/bin/php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/collect-diagnostics.php >> /var/log/lms-huawei-demo/diagnostics.log 2>&1
27 */6 * * * lms-huawei-demo /usr/bin/flock -n /var/lib/lms-huawei-demo-collector/collector.lock /usr/bin/php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/collect-onts.php >> /var/log/lms-huawei-demo/onts.log 2>&1
45 3 * * * lms-huawei-demo /usr/bin/flock -n /var/lib/lms-huawei-demo-collector/collector.lock /usr/bin/php /var/www/html/lms/plugins/LMSGponHuaweiDemoPlugin/bin/collect-inventory.php >> /var/log/lms-huawei-demo/inventory.log 2>&1
CRON
install -o root -g root -m 0644 "$cron_tmp" /etc/cron.d/lms-huawei-demo-collectors
rm -f "$cron_tmp"
cat > /etc/logrotate.d/lms-huawei-demo-collectors <<'ROTATE'
/var/log/lms-huawei-demo/*.log {
    su lms-huawei-demo lms-huawei-demo
    weekly
    rotate 8
    compress
    delaycompress
    missingok
    notifempty
    create 0640 lms-huawei-demo lms-huawei-demo
}
ROTATE
chown root:root /etc/logrotate.d/lms-huawei-demo-collectors
chmod 0644 /etc/logrotate.d/lms-huawei-demo-collectors
# Already preserved in the archive; no active schedule depends on this legacy script.
if command -v restorecon >/dev/null; then
    for path in /etc/cron.d/lms /etc/cron.d/lms-huawei-demo-collectors /etc/logrotate.d/lms-huawei-demo-collectors; do
        if test -e "$path"; then restorecon "$path"; fi
    done
fi
echo "Backup: $backup"
echo 'Collectors installed as lms-huawei-demo; read-only demo runtime ready.'
