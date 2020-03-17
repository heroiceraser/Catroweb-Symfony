#!/usr/bin/env bash

# https://github.com/Behat/Symfony2Extension/issues/149
# Behat/Symfony2Extension seems to have a permission problem
#
#  As a workaround we make sure the folders already exists with the correct permissions, else
#  var/cache/test/sessions will be created with wrong permissions
#
mkdir -p var/log/test
mkdir -p var/cache/test/sessions
## ~~

# Symfony 4+ Permissions
chmod -R 777 var/

# Since Symfony 4 this should not be necessary anymore when using APP_DEBUG = true
# But we set APP_DEBUG to false for our tests. Therefore no unmask() is called in index(_test).php bin/console.php.
# Therefore we still have to set the permissions like it had to be done in Symfony 3
HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
setfacl -R -m u:"$HTTPDUSER":rwX -m u:`whoami`:rwX var/cache var/log
setfacl -dR -m u:"$HTTPDUSER":rwX -m u:`whoami`:rwX var/cache var/log

# resource dirs must to be writable
chmod o+w public/resources/ -R
chmod o+w public/resources_test/ -R

# some test dirs must be writable
chmod o+w tests -R

# Jwt tokens need ssh keys with full access
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:catroweb
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:catroweb
chmod -R 777 config/jwt

echo "Permissions set"
