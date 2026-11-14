# Same pinned Alpine ABI as the PHP/Nginx consumers. No build tools in consumers.
ARG PHP_IMAGE=docker.io/library/php:8.4.27-fpm-alpine@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d
FROM ${PHP_IMAGE} AS build
RUN apk add --no-cache abuild build-base curl zlib-dev xz-dev perl tar python3 \
    && adduser -D builder && addgroup builder abuild
COPY . /backport/
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
FROM scratch
COPY --from=build /out/ /out/
