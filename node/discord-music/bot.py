import discord
from discord.ext import commands
import pafy
import logging
import youtube_dl

logging.basicConfig(filename='bot.log', level=logging.INFO)

TOKEN = 'token'
PREFIX = '-'

intents = discord.Intents.all()
intents.members = True

bot = commands.Bot(command_prefix=PREFIX, intents=intents)

@bot.event
async def on_ready():
    print(f'Logged in as {bot.user}')

@bot.event
async def on_message(message):
    if message.content.lower() == 'привет':
        await message.channel.send('Привет!')
    await bot.process_commands(message)

@bot.command()
async def play(ctx, url: str):
    if not ctx.author.voice:
        return await ctx.send("Вы не подключены к голосовому каналу!")
    voice_channel = ctx.author.voice.channel
    if ctx.voice_client is None:
        vc = await voice_channel.connect()
    else:
        await ctx.voice_client.move_to(voice_channel)
        vc = ctx.voice_client

    video = pafy.new(url)
    best = video.getbestaudio()
    source = await discord.FFmpegOpusAudio.from_probe(best.url, method='fallback')

    vc.play(source)

@bot.command()
async def leave(ctx):
    if ctx.voice_client:
        await ctx.guild.voice_client.disconnect()
    else:
        await ctx.send("Бот не подключен к голосовому каналу.")

logging.info('Bot Online')

bot.run("MTI4NzUzODM3NDEzOTE4MzEyNA.GXBr_6.ucLQQppRxE2nJGB8jO0sTrwRhJ0_tbvKNV7NCU")