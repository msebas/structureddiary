#!/bin/bash

# CONFIGURATION – adapt to your environment
DB="nextcloud"
USER="root"
PASSWORD="nextcloud"
HOST="localhost"
PORT=8212

echo "Disable app structureddiary"
docker exec  --user 33 master-nextcloud-1 php /var/www/html/occ app:disable structureddiary

# Generate DROP TABLE statements dynamically
echo "Listing tables to drop..."
echo "SET FOREIGN_KEY_CHECKS = 0;" > /tmp/drop_dp_tables.sql

mariadb --skip-ssl -h "$HOST" -u "$USER" -p"$PASSWORD" -P "$PORT" -Nse "
SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;')
FROM information_schema.tables
WHERE table_schema = '$DB'
AND table_name LIKE 'oc_sd_%';
" >> /tmp/drop_dp_tables.sql

echo "SET FOREIGN_KEY_CHECKS = 1;" >> /tmp/drop_dp_tables.sql

echo "The following DROP statements will be executed:"
cat /tmp/drop_dp_tables.sql
echo

# Execute the generated DROP statements
mariadb --skip-ssl -h "$HOST" -u "$USER" -p"$PASSWORD" -P "$PORT" "$DB" < /tmp/drop_dp_tables.sql

echo "Tables dropped."

# Remove migrations for structureddiary
echo "Removing migration entries..."
mariadb --skip-ssl -h "$HOST" -u "$USER" -p"$PASSWORD" -P "$PORT" "$DB" -e "
  DELETE FROM oc_migrations WHERE app = 'structureddiary';
"

# Remove app configuration leftovers (optional but recommended)
echo "Removing appconfig entries..."
mariadb --skip-ssl -h "$HOST" -u "$USER" -p"$PASSWORD" -P "$PORT" "$DB" -e "
DELETE FROM oc_appconfig WHERE appid = 'structureddiary';
"

echo "Cleanup completed."
rm /tmp/drop_dp_tables.sql
