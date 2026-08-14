FROM public.ecr.aws/lambda/nodejs:22

COPY package.json package-lock.json ${LAMBDA_TASK_ROOT}/
RUN npm ci --omit=optional --omit=dev

# Optionally replace the published serverlesswp package with a local build, so
# unreleased changes to it can be tested before publishing. Set
# SERVERLESSWP_LOCAL=/path/to/serverlesswp-node when running build-test.sh.
# The directory is empty unless that script packs a tarball into it.
ARG LOCAL_SERVERLESSWP=
COPY test/local-pkg/ /tmp/local-pkg/
RUN if [ -n "$LOCAL_SERVERLESSWP" ]; then \
        npm install --omit=optional --omit=dev "/tmp/local-pkg/$LOCAL_SERVERLESSWP" \
        && node -e "console.log('serverlesswp:', require('serverlesswp/package.json').version, 'autoPrependFile support:', typeof require('serverlesswp').buildPhpArgs === 'function')"; \
    fi

COPY api/ ${LAMBDA_TASK_ROOT}/api/
COPY util/ ${LAMBDA_TASK_ROOT}/util/
COPY wp/ ${LAMBDA_TASK_ROOT}/wp/
COPY test/installer.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/stream-wrapper-probe.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/stream-wrapper-wp-probe.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/minio-offload.php ${LAMBDA_TASK_ROOT}/wp/wp-content/mu-plugins/

CMD [ "api/index.handler" ]
