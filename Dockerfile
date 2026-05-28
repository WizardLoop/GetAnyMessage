FROM hub.madelineproto.xyz/danog/madelineproto:latest

# Copy files
COPY . /app/src

# Set working directory
WORKDIR /app/src

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Start bot
CMD ["php", "bot.php"]
