ARG PHP_IMAGE=docker.io/library/php:8.4.27-fpm-alpine@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d
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

# Upstream index supplied by the host; see docker/images.lock.json.
FROM docker.io/library/nginx:1.30.6-alpine@sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951
# Alpine v3.24 secdb fixes CVE-2026-111497 in this package revision.
RUN apk add --no-cache 'brotli-libs>=1.2.1-r0' 'curl>=8.23.0-r0' 'libcurl>=8.23.0-r0' \
    && apk info -v brotli-libs

COPY --from=libxml2_backport_build /out/ /tmp/libxml2-backport/
RUN cd /tmp/libxml2-backport && sha256sum -c SHA256SUMS \
    && cp ./*.rsa.pub /etc/apk/keys/ \
    && apk add --no-cache ./libxml2-2.13.9-r2000.apk \
    && apk info -v libxml2 \
    && rm -rf /tmp/libxml2-backport
