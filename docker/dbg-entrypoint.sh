#!/bin/bash
set -u
set -e

sed -i -e '/^echo.* Starting Apache.*/d' /tmp/docker_run.sh
sed -i -e '/^exec .*/d' /tmp/docker_run.sh

# Init MySQL database
set +e
RET=1
while [ $RET -ne 0 ]; do
    mysql -h $DB_SERVER -P $DB_PORT -u $DB_USER -p$DB_PASSWD -e "status" > /dev/null 2>&1
    RET=$?
    if [ $RET -ne 0 ]; then
        echo "Waiting for confirmation of MySQL service startup...";
        sleep 5
    fi
done
set -e

# If database doesn't exists yet, create it here.
if ! mysqlshow -h $DB_SERVER -P $DB_PORT -u $DB_USER -p$DB_PASSWD | grep -q $DB_NAME; then
    mysqladmin -h $DB_SERVER -P $DB_PORT -u $DB_USER -p$DB_PASSWD create $DB_NAME --force 2> /dev/null;
fi

# Execute upstream script
/tmp/docker_run.sh

# This need to be executed more than one time, go figure
if [ $PS_INSTALL_AUTO = 1 ]; then
    # In PS 1.7 the installation scripts fails sometimes.
    if [[ "$PS_VERSION" =~ ^1.7.* ]]; then
        OUT=""
        RE="successful"
        while [[ ! ${OUT} =~ $RE ]]; do
            # While not successful, keep trying
            export OUT="$(php /var/www/html/$PS_FOLDER_INSTALL/index_cli.php \
                        --domain="$PS_DOMAIN" \
                        --db_server=$DB_SERVER:$DB_PORT \
                        --db_name="$DB_NAME" \
                        --db_user=$DB_USER \
                        --db_password=$DB_PASSWD \
                        --prefix="$DB_PREFIX"\
                        --firstname="John" \
                        --lastname="Doe" \
                        --password=$ADMIN_PASSWD \
                        --email="$ADMIN_MAIL" \
                        --language=$PS_LANGUAGE \
                        --country=$PS_COUNTRY \
                        --newsletter=0 \
                        --send_email=0)"
        done
    fi
    
fi

if [ "${XDEBUG}" -eq 1 ]; then
    # Enable and configure Xdebug
    XDEBUG_LOG="/tmp/xdebug.log"
    XDEBUG_CFG="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
    docker-php-ext-enable xdebug

    (
    cat << EOF
xdebug.remote_enable = 1
xdebug.max_nesting_level=700
xdebug.remote_log='${XDEBUG_LOG}'

; Next five are defaults values but are added here as documentation.
xdebug.remote_autostart = 1
xdebug.remote_port = 9000
xdebug.remote_handler=dbgp
xdebug.profiler_enable = 0
xdebug.remote_connect_back = 0


EOF
    ) >> ${XDEBUG_CFG}

    # This way we allow www-data to write in the debugging log.
    if [ -e ${XDEBUG_LOG} ]; then
        rm ${XDEBUG_LOG}
    fi

    touch ${XDEBUG_LOG}
    chmod 777 ${XDEBUG_LOG}

    ## Try to infer docker host ip, this will not work on windows.
    # if XDEBUG_HOST is manually set
    HOST="${XDEBUG_HOST}"

    # else if check if is Docker for Mac
    if [ -z "${HOST}" ]; then
        HOST=`getent hosts docker.for.mac.localhost | awk '{ print $1 }'`
    fi

    # else get host ip
    if [ -z "${HOST}" ] || [ "${HOST}" = "::1" ]; then
        HOST=`/sbin/ip route | awk '/default/ { print $3 }'`
    fi

    if [ "${HOST}" ]; then
        echo "xdebug.remote_host = ${HOST}" >> ${XDEBUG_CFG}
    fi

    if [ ${XDEBUG_IDEKEY} ]; then
        echo "xdebug.idekey = ${XDEBUG_IDEKEY}" >> ${XDEBUG_CFG}
    fi

    # This one is needed to debug PHP CLI in PHPSTORM
    # this serverName is the automatically added by the IDE
    export PHP_IDE_CONFIG="serverName=$PS_DOMAIN"
    echo "export PHP_IDE_CONFIG=\"${PHP_IDE_CONFIG}\"" >> /root/.bashrc

    unset XDEBUG
    unset XDEBUG_HOST
    unset XDEBUG_IDEKEY
fi

# Remove PrestaShop install directory
rm -R /var/www/html/install

# Clone with local directory and move old container directory
cd /var/www
mv /var/www/html /var/www/html.container
rsync -a -l -t --delete /var/www/html.container/ /var/www/host/root/
ln -s /var/www/host/root /var/www/html
cp -a /var/www/host/module/pixelcrush /var/www/host/root/modules
rm -R /var/www/host/module
chown -R www-data:www-data /var/www/host/root

if [ "${PIXELCRUSH_USERID}" != "" ] && [ "${PIXELCRUSH_API_SECRET}" != "" ]; then  
    php -f /var/www/host/root/modules/pixelcrush/selfConfigPrompt.php \
        user_account="${PIXELCRUSH_USERID}" \
        api_secret="${PIXELCRUSH_API_SECRET}" \
        enable_images=1 \
        enable_statics=1
fi

if [ "${DEBUG_GROUP_ID}" != "" ]; then
    chown -R www-data.${DEBUG_GROUP_ID} /var/www/host/root
    find /var/www/host/root -type f -exec chmod 664 {} \;
    find /var/www/host/root -type d -exec chmod 775 {} \;
fi

umask 0002

# Use tmpfs as cache
rm -Rf /var/www/host/root/cache
ln -s /tmp/cache /var/www/host/root

if [[ "$PS_VERSION" =~ ^1.7.* ]]; then
    rm -Rf /var/www/host/root/app/cache
    ln -s /tmp/cache-1.7 /var/www/host/root/app/cache
fi

echo "Almost! Starting Apache now..";
exec apache2-foreground
