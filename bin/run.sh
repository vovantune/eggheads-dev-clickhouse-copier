#!/usr/bin/env sh

findCliPhp() {
    for TESTEXEC in php php-cli /usr/local/bin/php
    do
        SAPI=$(echo "<?= PHP_SAPI ?>" | $TESTEXEC 2>/dev/null)
        if [ "$SAPI" = "cli" ]
        then
            echo $TESTEXEC
            return
        fi
    done
    echo "Failed to find a CLI version of PHP; falling back to system standard php executable" >&2
    echo "php";
}


DIR=$(dirname -- "$0")

if [ -z "$PHP" ]
then
    PHP=$(findCliPhp)
fi

exec "$PHP" "$DIR"/run.php "$@"
