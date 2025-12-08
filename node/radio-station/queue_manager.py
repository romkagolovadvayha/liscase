"""
Менеджер очереди треков для радио-сервера
"""
import os
import random
import threading
from pathlib import Path
from typing import List, Optional, Dict
from mutagen import File
from mutagen.id3 import ID3NoHeaderError


class Track:
    """Класс для представления трека"""
    
    def __init__(self, file_path: Path):
        self.file_path = file_path
        self.name = file_path.name
        self.duration = 0
        self.artist = None
        self.title = None
        self._load_metadata()
    
    def _load_metadata(self):
        """Загрузить метаданные трека"""
        try:
            audio_file = File(str(self.file_path))
            if audio_file is not None:
                # Получаем длительность
                if hasattr(audio_file, 'info'):
                    self.duration = int(audio_file.info.length)
                
                # Получаем метаданные
                if hasattr(audio_file, 'tags'):
                    tags = audio_file.tags
                    if tags:
                        # Пробуем разные ключи для артиста и названия
                        self.artist = self._get_tag(tags, ['TPE1', 'ARTIST', '©ART', 'artist'])
                        self.title = self._get_tag(tags, ['TIT2', 'TITLE', '©nam', 'title'])
        except (ID3NoHeaderError, Exception) as e:
            # Если метаданные не найдены, используем имя файла
            pass
        
        # Если название не найдено, используем имя файла без расширения
        if not self.title:
            self.title = self.file_path.stem
    
    def _get_tag(self, tags, keys):
        """Получить значение тега по различным ключам"""
        for key in keys:
            if key in tags:
                value = tags[key]
                if isinstance(value, list) and len(value) > 0:
                    return str(value[0])
                elif value:
                    return str(value)
        return None
    
    def to_dict(self) -> Dict:
        """Преобразовать трек в словарь"""
        return {
            'name': self.name,
            'title': self.title or self.name,
            'artist': self.artist or 'Unknown Artist',
            'duration': self.duration,
            'file_path': str(self.file_path)
        }


class QueueManager:
    """Менеджер очереди треков"""
    
    def __init__(self, music_directory: str, shuffle: bool = False):
        self.music_directory = Path(music_directory)
        self.shuffle = shuffle
        self.playlist: List[Track] = []
        self.queue: List[Track] = []
        self.current_track: Optional[Track] = None
        self.current_position: int = 0
        self.listeners: set = set()
        self.lock = threading.Lock()
        self._load_playlist()
    
    def _load_playlist(self):
        """Загрузить плейлист из директории"""
        if not self.music_directory.exists():
            raise ValueError(f"Директория {self.music_directory} не существует")
        
        tracks = []
        for ext in ['.mp3', '.m4a', '.flac', '.ogg', '.wav', '.aac']:
            tracks.extend(self.music_directory.glob(f'*{ext}'))
            tracks.extend(self.music_directory.glob(f'**/*{ext}'))
        
        self.playlist = [Track(path) for path in tracks]
        
        if self.shuffle:
            random.shuffle(self.playlist)
        
        # Инициализируем очередь первым треком
        if self.playlist:
            self.queue = self.playlist.copy()
    
    def get_next_track(self) -> Optional[Track]:
        """Получить следующий трек из очереди"""
        with self.lock:
            if self.queue:
                self.current_track = self.queue.pop(0)
                self.current_position = 0
                return self.current_track
            elif self.playlist:
                # Если очередь пуста, начинаем заново
                self.queue = self.playlist.copy()
                if self.shuffle:
                    random.shuffle(self.queue)
                if self.queue:
                    self.current_track = self.queue.pop(0)
                    self.current_position = 0
                    return self.current_track
            return None
    
    def add_to_queue(self, track_name: str) -> bool:
        """Добавить трек в очередь по имени"""
        with self.lock:
            track = next((t for t in self.playlist if t.name == track_name), None)
            if track:
                self.queue.append(track)
                return True
            return False
    
    def add_to_queue_position(self, track_name: str, position: int) -> bool:
        """Добавить трек в очередь на определенную позицию"""
        with self.lock:
            track = next((t for t in self.playlist if t.name == track_name), None)
            if track:
                self.queue.insert(position, track)
                return True
            return False
    
    def remove_from_queue(self, position: int) -> bool:
        """Удалить трек из очереди по позиции"""
        with self.lock:
            if 0 <= position < len(self.queue):
                self.queue.pop(position)
                return True
            return False
    
    def clear_queue(self):
        """Очистить очередь"""
        with self.lock:
            self.queue.clear()
    
    def shuffle_queue(self):
        """Перемешать очередь"""
        with self.lock:
            random.shuffle(self.queue)
    
    def get_queue(self) -> List[Dict]:
        """Получить текущую очередь"""
        with self.lock:
            return [track.to_dict() for track in self.queue]
    
    def get_playlist(self) -> List[Dict]:
        """Получить полный плейлист"""
        with self.lock:
            return [track.to_dict() for track in self.playlist]
    
    def get_current_track(self) -> Optional[Dict]:
        """Получить текущий трек"""
        with self.lock:
            if self.current_track:
                return self.current_track.to_dict()
            return None
    
    def reload_playlist(self):
        """Перезагрузить плейлист из директории"""
        with self.lock:
            self._load_playlist()
    
    def add_listener(self, listener_id: str):
        """Добавить слушателя"""
        with self.lock:
            self.listeners.add(listener_id)
    
    def remove_listener(self, listener_id: str):
        """Удалить слушателя"""
        with self.lock:
            self.listeners.discard(listener_id)
    
    def get_listeners_count(self) -> int:
        """Получить количество слушателей"""
        with self.lock:
            return len(self.listeners)

