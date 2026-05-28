FROM hub.madelineproto.xyz/danog/madelineproto:latest

# Copy all bot files from your repo
COPY . /app/src

# Set the correct working directory
WORKDIR /app/src

# Run the bot
CMD ["php", "/app/src/bot.php"]
