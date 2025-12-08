"""
Основной сервер для потоковой передачи аудио
"""
import os
import sys
import time
import threading
from pathlib import Path
from typing import Generator
from flask import Flask, Response, request, jsonify
from werkzeug.serving import WSGIRequestHandler

# Добавляем текущую директорию в путь для импорта
sys.path.insert(0, str(Path(__file__).parent))

from queue_manager import QueueManager
from config import Config, get_music_directory, get_port


class RadioServer:
    """Класс радио-сервера"""
    
    def __init__(self, port: int = None, music_directory: str = None, shuffle: bool = False):
        self.port = port or get_port()
        self.music_directory = music_directory or get_music_directory()
        self.shuffle = shuffle
        self.app = Flask(__name__)
        self.queue_manager = QueueManager(self.music_directory, shuffle)
        self._setup_routes()
        self._listener_counter = 0
    
    def _setup_routes(self):
        """Настроить маршруты API"""
        
        @self.app.route('/stream')
        def stream():
            """Потоковая передача аудио"""
            listener_id = f"listener_{int(time.time() * 1000)}_{self._listener_counter}"
            self._listener_counter += 1
            self.queue_manager.add_listener(listener_id)
            
            def generate():
                """Генератор для потоковой передачи"""
                try:
                    while True:
                        track = self.queue_manager.get_next_track()
                        if not track:
                            # Если треков нет, отправляем тишину
                            time.sleep(1)
                            continue
                        
                        file_path = Path(track.file_path)
                        if not file_path.exists():
                            continue
                        
                        # Открываем файл и читаем по частям
                        with open(file_path, 'rb') as audio_file:
                            while True:
                                chunk = audio_file.read(Config.CHUNK_SIZE)
                                if not chunk:
                                    break
                                yield chunk
                except GeneratorExit:
                    # Клиент отключился
                    pass
                finally:
                    self.queue_manager.remove_listener(listener_id)
            
            response = Response(
                generate(),
                mimetype='audio/mpeg',
                headers={
                    'Content-Type': 'audio/mpeg',
                    'Cache-Control': 'no-cache',
                    'Connection': 'keep-alive',
                    'Access-Control-Allow-Origin': '*'
                }
            )
            return response
        
        @self.app.route('/api/status', methods=['GET'])
        def status():
            """Получить статус сервера"""
            current = self.queue_manager.get_current_track()
            queue = self.queue_manager.get_queue()
            playlist = self.queue_manager.get_playlist()
            
            return jsonify({
                'status': 'running',
                'port': self.port,
                'music_directory': str(self.music_directory),
                'listeners': self.queue_manager.get_listeners_count(),
                'current': current,
                'queue': queue,
                'queue_length': len(queue),
                'playlist': playlist,
                'total_tracks': len(playlist),
                'shuffle': self.shuffle
            })
        
        @self.app.route('/api/current', methods=['GET'])
        def current():
            """Получить текущий трек"""
            current_track = self.queue_manager.get_current_track()
            return jsonify({
                'current': current_track
            })
        
        @self.app.route('/api/queue', methods=['GET'])
        def get_queue():
            """Получить очередь треков"""
            queue = self.queue_manager.get_queue()
            return jsonify({
                'queue': queue,
                'length': len(queue)
            })
        
        @self.app.route('/api/queue', methods=['POST'])
        def add_to_queue():
            """Добавить трек в очередь"""
            data = request.get_json()
            track_name = data.get('track_name')
            position = data.get('position')
            
            if not track_name:
                return jsonify({'error': 'track_name is required'}), 400
            
            if position is not None:
                success = self.queue_manager.add_to_queue_position(track_name, position)
            else:
                success = self.queue_manager.add_to_queue(track_name)
            
            if success:
                return jsonify({'success': True, 'message': 'Track added to queue'})
            else:
                return jsonify({'error': 'Track not found'}), 404
        
        @self.app.route('/api/queue/<int:position>', methods=['DELETE'])
        def remove_from_queue(position):
            """Удалить трек из очереди"""
            success = self.queue_manager.remove_from_queue(position)
            if success:
                return jsonify({'success': True, 'message': 'Track removed from queue'})
            else:
                return jsonify({'error': 'Invalid position'}), 404
        
        @self.app.route('/api/queue/clear', methods=['POST'])
        def clear_queue():
            """Очистить очередь"""
            self.queue_manager.clear_queue()
            return jsonify({'success': True, 'message': 'Queue cleared'})
        
        @self.app.route('/api/queue/shuffle', methods=['POST'])
        def shuffle_queue():
            """Перемешать очередь"""
            self.queue_manager.shuffle_queue()
            return jsonify({'success': True, 'message': 'Queue shuffled'})
        
        @self.app.route('/api/playlist', methods=['GET'])
        def get_playlist():
            """Получить полный плейлист"""
            playlist = self.queue_manager.get_playlist()
            return jsonify({
                'playlist': playlist,
                'total': len(playlist)
            })
        
        @self.app.route('/api/playlist/reload', methods=['POST'])
        def reload_playlist():
            """Перезагрузить плейлист"""
            try:
                self.queue_manager.reload_playlist()
                return jsonify({'success': True, 'message': 'Playlist reloaded'})
            except Exception as e:
                return jsonify({'error': str(e)}), 500
        
        @self.app.route('/api/listeners', methods=['GET'])
        def get_listeners():
            """Получить количество слушателей"""
            return jsonify({
                'listeners': self.queue_manager.get_listeners_count()
            })
        
        @self.app.route('/api/next', methods=['POST'])
        def skip_to_next():
            """Перейти к следующему треку"""
            # Очищаем текущий трек, чтобы следующий запрос к /stream начал новый трек
            self.queue_manager.current_track = None
            return jsonify({'success': True, 'message': 'Skipped to next track'})
    
    def run(self, host='0.0.0.0', debug=False):
        """Запустить сервер"""
        print(f"🎵 Запуск радио-сервера на порту {self.port}")
        print(f"📁 Директория с музыкой: {self.music_directory}")
        print(f"🎲 Shuffle: {self.shuffle}")
        print(f"🌐 Stream URL: http://{host}:{self.port}/stream")
        print(f"📡 API URL: http://{host}:{self.port}/api/status")
        print("\nДля остановки нажмите Ctrl+C\n")
        
        # Отключаем логирование запросов для /stream
        class QuietHandler(WSGIRequestHandler):
            def log_request(self, code='-', size='-'):
                if '/stream' not in self.path:
                    super().log_request(code, size)
        
        self.app.run(host=host, port=self.port, debug=debug, request_handler=QuietHandler)

