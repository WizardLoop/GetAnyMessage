FROM hub.madelineproto.xyz/danog/madelineproto:latest

# Copy all files from repo to container
COPY . /app/src

# Set working directory correctly
WORKDIR /app/src

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Run the bot (this is the most reliable way)
CMD php bot.php
