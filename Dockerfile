FROM hub.madelineproto.xyz/danog/madelineproto:latest

COPY . /app

WORKDIR /app
RUN composer install --no-dev --optimize-autoloader

WORKDIR /app/src

CMD sh -c ' \
  echo "API_ID=${API_ID}" > .env && \
  echo "API_HASH=${API_HASH}" >> .env && \
  echo "BOT_TOKEN=${BOT_TOKEN}" >> .env && \
  echo "ADMIN=${ADMIN}" >> .env && \
  echo "BOT_NAME=${BOT_NAME:-GetAnyMessage}" >> .env && \
  echo "DB_FLAG=${DB_FLAG:-no}" >> .env && \
  php bot.php'
