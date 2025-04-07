FROM public.ecr.aws/lambda/nodejs:22

COPY package.json ${LAMBDA_TASK_ROOT}/
RUN npm install --omit=optional --omit=dev

COPY api/ ${LAMBDA_TASK_ROOT}/api/
COPY util/ ${LAMBDA_TASK_ROOT}/util/
COPY wp/ ${LAMBDA_TASK_ROOT}/wp/
COPY test/installer.php ${LAMBDA_TASK_ROOT}/wp/

CMD [ "api/index.handler" ]