# Build tooling only: no Go/Node/npm/compiler is copied into the runtime.
FROM docker.io/library/golang:1.26.10-alpine3.24@sha256:a2012b4a1d1f9f7e9f44e0d39b805a901e856b1dd80b4e6ee4338db547050308 AS build
RUN apk add --no-cache nodejs npm git ca-certificates \
    && adduser -D builder \
    && mkdir -p /home/builder/work /home/builder/out \
    && chown -R builder:builder /home/builder
COPY --chmod=0555 build.sh /usr/local/bin/build-mailpit
ENV GOTOOLCHAIN=local GOPROXY=https://proxy.golang.org GOSUMDB=sum.golang.org \
    GOPATH=/home/builder/go GOCACHE=/home/builder/.cache/go-build
USER builder
WORKDIR /home/builder/work
RUN /usr/local/bin/build-mailpit

# Keep upstream runtime paths/entrypoint, replace only the verified binary.
FROM docker.io/axllent/mailpit:v1.32.0@sha256:0790692be935ea4420d085694f7985b1f692ca6a8771ab0e0bd8ba75ac180498 AS runtime_files
COPY --from=build /home/builder/out/mailpit /mailpit
COPY --from=build /home/builder/out/evidence/ /usr/share/notification-hub/mailpit-build/
# Copy the merged filesystem; discard the layer containing the replaced binary.
FROM scratch
COPY --from=runtime_files / /
EXPOSE 1025 1110 8025
HEALTHCHECK --interval=15s --start-period=10s CMD ["/mailpit", "readyz"]
ENTRYPOINT ["/mailpit"]
LABEL org.opencontainers.image.licenses="MIT" \
    org.opencontainers.image.title="Mailpit" \
    org.opencontainers.image.source="https://github.com/axllent/mailpit" \
    org.opencontainers.image.version="1.32.0+nh.xnet0.62.0"
USER 65534:65534
