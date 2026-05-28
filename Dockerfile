FROM hub.madelineproto.xyz/danog/madelineproto:latest

WORKDIR /app/src

# Copy all bot files
COPY . /app/src

# Run the bot
CMD ["php", "bot.php"]
