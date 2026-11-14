# Image indexes recorded in docker/images.lock.json (host-supplied evidence).
ARG PHP_IMAGE=docker.io/library/php:8.4.27-fpm-alpine@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d
ARG COMPOSER_IMAGE=docker.io/library/composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc

FROM ${PHP_IMAGE} AS libxml2_backport_build
RUN apk add --no-cache abuild build-base curl zlib-dev xz-dev perl tar python3 \
    && adduser -D builder && addgroup builder abuild
COPY --from=libxml2_backport . /backport/
# Normalize public build inputs; host permissions may be 0700/0600.
RUN chmod 0755 /backport \
    && chmod 0644 /backport/APKBUILD /backport/CVE-2026-86140.patch /backport/bounds-check.py \
    && mkdir -p /home/builder/backports/libxml2 \
    && cp /backport/APKBUILD /backport/CVE-2026-86140.patch /home/builder/backports/libxml2/ \
    && chown -R builder:builder /home/builder
USER builder
WORKDIR /home/builder/backports/libxml2
# Signing key is generated inside this discarded builder; it is never exported.
RUN abuild-keygen -a -n
# apk verifies generated packages while abuild creates the repository index.
# Trust only the public key generated in this builder; keep the private key local.
USER root
RUN cp /home/builder/.abuild/*.rsa.pub /etc/apk/keys/
USER builder
RUN abuild -d
USER root
RUN mkdir /out \
    && cp /home/builder/packages/backports/*/libxml2-2.13.9-r2000.apk /out/ \
    && cp /home/builder/.abuild/*.rsa.pub /out/ \
    && cd /out && sha256sum ./*.apk ./*.pub > SHA256SUMS

FROM ${COMPOSER_IMAGE} AS composer_binary

FROM ${PHP_IMAGE} AS extensions
ARG REDIS_EXTENSION_VERSION=6.2.0
RUN apk add --no-cache --virtual .extension-build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl zip \
    && pecl install redis-${REDIS_EXTENSION_VERSION} \
    && docker-php-ext-enable redis

FROM ${PHP_IMAGE} AS development
ARG APP_UID=1000
ARG APP_GID=1000
# The official PHP base already includes PDO SQLite and mbstring; assert below.
RUN apk add --no-cache ca-certificates curl fcgi icu-libs libzip unzip 'brotli-libs>=1.2.1-r0' 'curl>=8.23.0-r0' 'libcurl>=8.23.0-r0' \
    && update-ca-certificates
COPY --from=libxml2_backport_build /out/ /tmp/libxml2-backport/
RUN cd /tmp/libxml2-backport && sha256sum -c SHA256SUMS \
    && cp ./*.rsa.pub /etc/apk/keys/ \
    && apk add --no-cache ./libxml2-2.13.9-r2000.apk \
    && apk info -v libxml2 \
    && rm -rf /tmp/libxml2-backport
# Reuse an existing numeric group (for example a host GID already used by Alpine).
RUN set -eu; \
    case "$APP_UID" in ''|*[!0-9]*) echo "APP_UID must be numeric" >&2; exit 1;; esac; \
    case "$APP_GID" in ''|*[!0-9]*) echo "APP_GID must be numeric" >&2; exit 1;; esac; \
    test "$APP_UID" -gt 0 && test "$APP_GID" -gt 0 || { echo "Use non-root APP_UID and APP_GID" >&2; exit 1; }; \
    if awk -F: -v uid="$APP_UID" '$3 == uid { found=1 } END { exit !found }' /etc/passwd; then \
        echo "APP_UID is already assigned in the base image; choose an unused non-root UID" >&2; exit 1; \
    fi; \
    app_group="$(awk -F: -v gid="$APP_GID" '$3 == gid { print $1; exit }' /etc/group)"; \
    if [ -z "$app_group" ]; then addgroup -g "$APP_GID" app; app_group=app; fi; \
    adduser -D -u "$APP_UID" -G "$app_group" -h /home/app app
COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=composer_binary /usr/bin/composer /usr/local/bin/composer
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'session.upload_progress.enabled=0' \
        'curl.cainfo=/etc/ssl/certs/ca-certificates.crt' \
        'openssl.cafile=/etc/ssl/certs/ca-certificates.crt' \
        'expose_php=Off' \
        > "$PHP_INI_DIR/conf.d/zz-notification-hub.ini" \
    && php -r 'if (version_compare(PHP_VERSION, "8.4.27", "<")) { exit(1); } foreach (["bcmath", "curl", "dom", "intl", "mbstring", "pcntl", "pdo_sqlite", "redis", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: ".$extension.PHP_EOL); exit(1); } }' \
    && composer --version --no-ansi \
    && ! command -v gcc \
    && ! command -v make
ENV COMPOSER_HOME=/home/app/.composer
WORKDIR /var/www
COPY --chown=${APP_UID}:${APP_GID} . /var/www
RUN mkdir -p storage/app storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache /home/app/.composer \
    && chown -R "$APP_UID:$APP_GID" storage bootstrap/cache /home/app/.composer \
    && chmod -R u+rwX storage bootstrap/cache
USER app
EXPOSE 9000
CMD ["php-fpm", "-F"]
