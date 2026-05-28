FROM hub.madelineproto.xyz/danog/madelineproto:latest

COPY . /app

WORKDIR /app

RUN composer install --no-dev --optimize-autoloader && \
    mkdir -p /app/src/session && \
    chmod -R 777 /app/src/session

WORKDIR /app/src

EXPOSE 8000

CMD sh -c ' \
  echo "API_ID=${API_ID}" > .env && \
  echo "API_HASH=${API_HASH}" >> .env && \
  echo "BOT_TOKEN=${BOT_TOKEN}" >> .env && \
  echo "ADMIN=${ADMIN}" >> .env && \
  echo "BOT_NAME=${BOT_NAME:-GetAnyMessage}" >> .env && \
  echo "DB_FLAG=${DB_FLAG:-yes}" >> .env && \
  echo "DB_HOST=${DB_HOST}" >> .env && \
  echo "DB_PORT=${DB_PORT}" >> .env && \
  echo "DB_USER=${DB_USER}" >> .env && \
  echo "DB_PASS=${DB_PASS}" >> .env && \
  echo "DB_NAME=${DB_NAME}" >> .env && \
  echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory.ini && \
  php -d detect_unicode=0 -S 0.0.0.0:8000 -t /tmp & \
  exec php bot.php'
