FROM hub.madelineproto.xyz/danog/madelineproto:latest

# Copy all files from repo to container
COPY . /app

# Set working directory
WORKDIR /app/src

# Install dependencies (from repo root where composer.json lives)
RUN cd /app && composer install --no-dev --optimize-autoloader

# Run the bot from the src directory
CMD ["php", "bot.php"]
