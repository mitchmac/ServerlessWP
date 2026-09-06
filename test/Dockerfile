FROM public.ecr.aws/lambda/nodejs:22

COPY package.json package-lock.json ${LAMBDA_TASK_ROOT}/

# A local tarball lets this fixture validate an unpublished package release.
ARG LOCAL_SERVERLESSWP=
COPY test/local-pkg/ /tmp/local-pkg/
RUN if [ -n "$LOCAL_SERVERLESSWP" ]; then \
        npm install --package-lock-only --ignore-scripts "/tmp/local-pkg/$LOCAL_SERVERLESSWP"; \
    fi \
    && npm ci --omit=optional --omit=dev

COPY api/ ${LAMBDA_TASK_ROOT}/api/
COPY wp/ ${LAMBDA_TASK_ROOT}/wp/
COPY test/installer.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/stream-wrapper-probe.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/stream-wrapper-wp-probe.php ${LAMBDA_TASK_ROOT}/wp/
COPY test/minio-offload.php ${LAMBDA_TASK_ROOT}/wp/wp-content/mu-plugins/

CMD [ "api/index.handler" ]
