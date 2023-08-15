FROM public.ecr.aws/lambda/nodejs:18

COPY package.json ${LAMBDA_TASK_ROOT}/
RUN npm install --omit=optional --omit=dev

COPY api/ ${LAMBDA_TASK_ROOT}/api/
COPY util/ ${LAMBDA_TASK_ROOT}/util/
COPY wp/ ${LAMBDA_TASK_ROOT}/wp/
COPY temp/sqlite-database-integration ${LAMBDA_TASK_ROOT}/wp/wp-content/plugins/sqlite-database-integration/
COPY /temp/sqlite-database-integration/db.copy ${LAMBDA_TASK_ROOT}/wp/wp-content/db.php
COPY test/installer.php ${LAMBDA_TASK_ROOT}/wp/

RUN sed -i 's#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/tmp/wp/wp-content/plugins/sqlite-database-integration#' ${LAMBDA_TASK_ROOT}/wp/wp-content/db.php

RUN echo "define('FQDBDIR', '/tmp/db' );" >>  ${LAMBDA_TASK_ROOT}/wp/wp-config.php

CMD [ "api/index.handler" ]