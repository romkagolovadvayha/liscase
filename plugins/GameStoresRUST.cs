// #define TESTING

using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text.RegularExpressions;
using Newtonsoft.Json;
using Newtonsoft.Json.Converters;
using Newtonsoft.Json.Linq;
using Oxide.Core;
using Oxide.Core.Configuration;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using Steamworks;
using UnityEngine;
using UnityEngine.UI;
using UnityEngine.Networking;

#if CARBON
using Carbon.Base;
using Carbon.Modules;
#endif

namespace Oxide.Plugins
{
	[Info("GameStoresRUST", "HOUGAN & Sstine & rostov114 & Mevent # GAMESTORES", "1.0.23")]
	public class GameStoresRUST : RustPlugin
	{
		private class ImageCache
		{
			private const string _path = "GameStoresRUST/Images/";
			private const string _itemsPath = "GameStoresRUST/Images/items/";
			private const string _printPath = "data/" + _path;
			private readonly Dictionary<string, ImageData> _images = new()
			{
				{ "card", new ImageData { Url = "https://storage.prostoj.store/plugin-file/card.png" } },
				{ "card_success", new ImageData { Url = "https://storage.prostoj.store/plugin-file/card_success.png" } },
				{ "card_red", new ImageData { Url = "https://storage.prostoj.store/plugin-file/card_red.png" } },
				{ "card_gray", new ImageData { Url = "https://storage.prostoj.store/plugin-file/card_gray.png" } },
				{ "card_popular", new ImageData { Url = "https://storage.prostoj.store/plugin-file/card_popular.png" } },
				{ "light", new ImageData { Url = "https://storage.prostoj.store/plugin-file/light.png" } },
				{ "bg_right", new ImageData { Url = "https://storage.prostoj.store/plugin-file/bg_right.png" } },
				{ "modal_background", new ImageData { Url = "https://storage.prostoj.store/plugin-file/modal_background.png" } },
				{ "spliter", new ImageData { Url = "https://storage.prostoj.store/plugin-file/spliter.png" } },
				{ "menu_punkt_bg", new ImageData { Url = "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png" } }
			};

			// Кеш для изображений предметов (динамические изображения из API)
			private readonly Dictionary<string, ImageData> _itemImages = new();

			private enum ImageStatus
			{
				NotLoaded,
				Loading,
				Loaded,
				Failed
			}

			private class ImageData
			{
				public ImageStatus Status = ImageStatus.NotLoaded;
				public string Id { get; set; }
				public string Url { get; set; }
			}

			private GameStoresRUST _instance;
			private bool _isUnloaded = false;
			private int _loadedCount = 0;
			private int _totalCount = 0;

			public ImageCache(GameStoresRUST instance)
			{
				_instance = instance;
				_totalCount = _images.Count;
				EnsureDirectoryExists();
				EnsureItemsDirectoryExists();
			}

			private void EnsureDirectoryExists()
			{
				try
				{
					string dataDir = Interface.Oxide.DataDirectory;
					if (string.IsNullOrEmpty(dataDir))
					{
						if (_instance != null)
							_instance.PrintError("GameStoresRUST: DataDirectory is null or empty!");
						return;
					}

					// Используем Path.Combine для правильного формирования пути
					string directory = Path.Combine(dataDir, _path.TrimEnd('/', '\\'));

					if (!Directory.Exists(directory))
					{
						Directory.CreateDirectory(directory);

						// Проверяем, что каталог действительно создан
						if (!Directory.Exists(directory))
						{
							if (_instance != null)
							{
								_instance.PrintError($"GameStoresRUST: Failed to create directory: {directory}");
								_instance.PrintError($"GameStoresRUST: Check file permissions for: {dataDir}");
							}
						}
					}
				}
				catch (Exception ex)
				{
					if (_instance != null)
					{
						_instance.PrintError($"GameStoresRUST: Exception creating directory {_printPath}");
						_instance.PrintError($"GameStoresRUST: Error: {ex.Message}");
						_instance.PrintError($"GameStoresRUST: Stack trace: {ex.StackTrace}");
					}
					else
					{
						System.Console.WriteLine($"GameStoresRUST: ERROR - Exception in EnsureDirectoryExists and _instance is null: {ex.Message}");
					}
				}
			}

			public void DownloadImages()
			{
				if (_isUnloaded) return; // Не загружаем изображения, если плагин выгружен

				foreach (var image in _images)
				{
					if (image.Value.Status == ImageStatus.NotLoaded)
					{
						image.Value.Status = ImageStatus.Loading;
						ServerMgr.Instance.StartCoroutine(ProcessDownloadImage(image.Key, image.Value));
					}
				}
			}

			public void StopAllDownloads()
			{
				_isUnloaded = true;
				// Помечаем все загружающиеся изображения как не загруженные, чтобы остановить корутины
				foreach (var image in _images)
				{
					if (image.Value.Status == ImageStatus.Loading)
					{
						image.Value.Status = ImageStatus.NotLoaded;
					}
				}
				// Останавливаем загрузку изображений товаров
				foreach (var itemImage in _itemImages)
				{
					if (itemImage.Value.Status == ImageStatus.Loading)
					{
						itemImage.Value.Status = ImageStatus.NotLoaded;
					}
				}
			}

			private IEnumerator ProcessDownloadImage(string imageName, ImageData imageData)
			{
				if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

				// Сначала пытаемся загрузить из локальной папки
				string dataDir = Interface.Oxide.DataDirectory;
				string directory = Path.Combine(dataDir, _path.TrimEnd('/', '\\'));
				string localPath = Path.Combine(directory, imageName + ".png");
				string localUrl = "file://" + localPath;

				// Убеждаемся, что каталог существует
				if (!Directory.Exists(directory))
				{
					try
					{
						Directory.CreateDirectory(directory);
					}
					catch (Exception ex)
					{
						_instance?.PrintError($"GameStoresRUST: Failed to create directory {directory}: {ex.Message}");
					}
				}

				using (UnityWebRequest www = UnityWebRequestTexture.GetTexture(localUrl))
				{
					yield return www.SendWebRequest();

					if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

					if (www.isNetworkError || www.isHttpError)
					{
						// Если локального файла нет, загружаем с URL
						// Локальное изображение не найдено, загружаем с URL (не выводим сообщение для уменьшения шума в логах)
						using (UnityWebRequest wwwRemote = UnityWebRequestTexture.GetTexture(imageData.Url))
						{
							yield return wwwRemote.SendWebRequest();

							if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

							if (wwwRemote.isNetworkError || wwwRemote.isHttpError)
							{
								imageData.Status = ImageStatus.Failed;
								_instance?.PrintError($"Failed to load image: {imageName} from {imageData.Url}");
								_loadedCount++;
								CheckAllLoaded();
							}
							else
							{
								Texture2D tex = DownloadHandlerTexture.GetContent(wwwRemote);
								imageData.Id = FileStorage.server.Store(tex.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString();
								imageData.Status = ImageStatus.Loaded;

								// Сохраняем в локальную папку для следующего раза
								SaveImageToLocal(tex, imageName);
								UnityEngine.Object.DestroyImmediate(tex);
								_loadedCount++;
								CheckAllLoaded();
							}
						}
					}
					else
					{
						// Локальный файл найден, используем его
						Texture2D tex = DownloadHandlerTexture.GetContent(www);
						imageData.Id = FileStorage.server.Store(tex.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString();
						imageData.Status = ImageStatus.Loaded;
						UnityEngine.Object.DestroyImmediate(tex);
						_loadedCount++;
						CheckAllLoaded();
					}
				}
			}

			private void CheckAllLoaded()
			{
				// Все изображения загружены (не выводим сообщение для уменьшения шума в логах)
			}

			private void SaveImageToLocal(Texture2D texture, string imageName)
			{
				try
				{
					string dataDir = Interface.Oxide.DataDirectory;
					string directory = Path.Combine(dataDir, _path.TrimEnd('/', '\\'));

					if (!Directory.Exists(directory))
					{
						Directory.CreateDirectory(directory);
					}

					string filePath = Path.Combine(directory, imageName + ".png");
					File.WriteAllBytes(filePath, texture.EncodeToPNG());
					// Изображение сохранено (не выводим сообщение для уменьшения шума в логах)
				}
				catch (Exception ex)
				{
					_instance?.PrintError($"GameStoresRUST: Failed to save image {imageName} to local: {ex.Message}");
					_instance?.PrintError($"GameStoresRUST: Stack trace: {ex.StackTrace}");
				}
			}

			public string GetImageId(string name)
			{
				if (_images.TryGetValue(name, out ImageData imageData) && imageData.Status == ImageStatus.Loaded)
				{
					return imageData.Id;
				}
				return null;
			}

			public bool IsImageLoaded(string name)
			{
				return _images.TryGetValue(name, out ImageData imageData) && imageData.Status == ImageStatus.Loaded;
			}

			private void EnsureItemsDirectoryExists()
			{
				try
				{
					string dataDir = Interface.Oxide.DataDirectory;
					if (string.IsNullOrEmpty(dataDir))
					{
						if (_instance != null)
							_instance.PrintError("GameStoresRUST: DataDirectory is null or empty!");
						return;
					}

					string directory = Path.Combine(dataDir, _itemsPath.TrimEnd('/', '\\'));

					if (!Directory.Exists(directory))
					{
						Directory.CreateDirectory(directory);
						// Директория создана (не выводим сообщение для уменьшения шума в логах)
					}
				}
				catch (Exception ex)
				{
					_instance?.PrintError($"GameStoresRUST: Exception creating items directory: {ex.Message}");
				}
			}

			// Кеширование изображения предмета из API
			public void CacheItemImage(string fileName, string url)
			{
				if (_isUnloaded) return; // Не загружаем изображения, если плагин выгружен

				if (string.IsNullOrEmpty(fileName) || string.IsNullOrEmpty(url))
				{
					_instance?.PrintWarning($"GameStoresRUST: CacheItemImage skipped - fileName or url is empty. fileName: {fileName}, url: {url}");
					return;
				}

				// Нормализуем URL: заменяем HTTP на HTTPS для безопасности
				if (url.StartsWith("http://", StringComparison.OrdinalIgnoreCase))
				{
					url = url.Replace("http://", "https://", StringComparison.OrdinalIgnoreCase);
				}

				// Проверяем, не загружается ли уже это изображение
				if (_itemImages.TryGetValue(fileName, out ImageData existingData))
				{
					if (existingData.Status == ImageStatus.Loading || existingData.Status == ImageStatus.Loaded)
					{
						return; // Уже загружается или загружено
					}
				}

				// Проверяем, что URL валидный (должен начинаться с http:// или https://)
				// Если это не URL, а item ID или другой невалидный формат, не пытаемся загружать
				if (!url.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
				    !url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
				{
					return;
				}

				// Убеждаемся, что директория существует перед началом загрузки
				EnsureItemsDirectoryExists();

				// Добавляем в словарь и начинаем загрузку (не выводим сообщение для уменьшения шума в логах)
				var imageData = new ImageData { Url = url, Status = ImageStatus.Loading };
				_itemImages[fileName] = imageData;

				ServerMgr.Instance.StartCoroutine(ProcessDownloadItemImage(fileName, imageData));
			}

			private IEnumerator ProcessDownloadItemImage(string fileName, ImageData imageData)
			{
				if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

				// Сначала пытаемся загрузить из локальной папки items/
				string dataDir = Interface.Oxide.DataDirectory;
				if (string.IsNullOrEmpty(dataDir))
				{
					_instance?.PrintError($"GameStoresRUST: DataDirectory is null when processing {fileName}");
					imageData.Status = ImageStatus.Failed;
					yield break;
				}

				string directory = Path.Combine(dataDir, _itemsPath.TrimEnd('/', '\\'));

				// Очищаем fileName от недопустимых символов для имени файла
				string safeFileName = fileName.Replace(":", "_").Replace("/", "_").Replace("\\", "_").Replace("*", "_").Replace("?", "_").Replace("\"", "_").Replace("<", "_").Replace(">", "_").Replace("|", "_");

				string localPath = Path.Combine(directory, safeFileName + ".png");
				string localUrl = "file://" + localPath;

				// Проверяем локальный файл (не выводим сообщение для уменьшения шума в логах)

				// Убеждаемся, что каталог существует
					if (!Directory.Exists(directory))
					{
						try
						{
							Directory.CreateDirectory(directory);
						}
						catch (Exception ex)
						{
							_instance?.PrintError($"GameStoresRUST: Failed to create items directory {directory}: {ex.Message}");
						}
					}

				using (UnityWebRequest www = UnityWebRequestTexture.GetTexture(localUrl))
				{
					yield return www.SendWebRequest();

					if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

					if (www.isNetworkError || www.isHttpError)
					{
						// Если локального файла нет, загружаем с URL
						using (UnityWebRequest wwwRemote = UnityWebRequestTexture.GetTexture(imageData.Url))
						{
							yield return wwwRemote.SendWebRequest();

							if (_isUnloaded) yield break; // Останавливаем, если плагин выгружен

							if (wwwRemote.isNetworkError || wwwRemote.isHttpError)
							{
								imageData.Status = ImageStatus.Failed;
								// Не выводим сообщение об ошибке - это нормально, если изображение недоступно
							}
							else
							{
								Texture2D tex = DownloadHandlerTexture.GetContent(wwwRemote);
								if (tex != null)
								{
									imageData.Id = FileStorage.server.Store(tex.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString();
									imageData.Status = ImageStatus.Loaded;

									// Сохраняем в локальную папку items/ для следующего раза
									SaveItemImageToLocal(tex, fileName);
									UnityEngine.Object.DestroyImmediate(tex);
								}
								else
								{
									imageData.Status = ImageStatus.Failed;
								}
							}
						}
					}
					else
					{
						// Локальный файл найден, используем его
						Texture2D tex = DownloadHandlerTexture.GetContent(www);
						if (tex != null)
						{
							imageData.Id = FileStorage.server.Store(tex.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString();
							imageData.Status = ImageStatus.Loaded;
							UnityEngine.Object.DestroyImmediate(tex);
						}
						else
						{
							imageData.Status = ImageStatus.Failed;
						}
					}
				}
			}

			private void SaveItemImageToLocal(Texture2D texture, string fileName)
			{
				try
				{
					string dataDir = Interface.Oxide.DataDirectory;
					if (string.IsNullOrEmpty(dataDir))
					{
						_instance?.PrintError($"GameStoresRUST: DataDirectory is null or empty when saving {fileName}");
						return;
					}

					string directory = Path.Combine(dataDir, _itemsPath.TrimEnd('/', '\\'));

					if (!Directory.Exists(directory))
					{
						Directory.CreateDirectory(directory);
					}

					// Очищаем fileName от недопустимых символов для имени файла
					string safeFileName = fileName.Replace(":", "_").Replace("/", "_").Replace("\\", "_").Replace("*", "_").Replace("?", "_").Replace("\"", "_").Replace("<", "_").Replace(">", "_").Replace("|", "_");

					string filePath = Path.Combine(directory, safeFileName + ".png");

					byte[] pngData = texture.EncodeToPNG();
					if (pngData == null || pngData.Length == 0)
					{
						_instance?.PrintError($"GameStoresRUST: Failed to encode texture to PNG for {fileName}");
						return;
					}

					File.WriteAllBytes(filePath, pngData);
					// Изображение успешно сохранено (не выводим сообщение для уменьшения шума в логах)
				}
				catch (Exception ex)
				{
					_instance?.PrintError($"GameStoresRUST: Failed to save item image {fileName} to local: {ex.Message}\nStack trace: {ex.StackTrace}");
				}
			}

			// Получить ID кешированного изображения предмета
			public string GetItemImageId(string fileName)
			{
				if (_itemImages.TryGetValue(fileName, out ImageData imageData) && imageData.Status == ImageStatus.Loaded)
				{
					return imageData.Id;
				}
				return null;
			}

			// Проверить, загружено ли изображение предмета
			public bool IsItemImageLoaded(string fileName)
			{
				return _itemImages.TryGetValue(fileName, out ImageData imageData) && imageData.Status == ImageStatus.Loaded;
			}
		}
		#region Fields

		[PluginReference] private Plugin
			GameStoresWipeBlock = null,
			ImageLibrary = null,
			NoEscape = null,
			Notify = null;

		private static GameStoresRUST Instance;

#if CARBON
		private ImageDatabaseModule imageDatabase = BaseModule.GetModule<ImageDatabaseModule>();
#endif

		private bool initialization, Initialized, maintenance;

		private string ShopURL = "UNDEFINED";
		private int StartBalance;

		private Dictionary<int, Dictionary<ulong, int>> ListTimeOutCommand = new();

		private HashSet<ulong> ListBannedCommandUserID = new();

		private Dictionary<ulong, PlayerBasket> _playerBaskets = new();
		private Dictionary<ulong, int> _playerPages = new(); // Хранение текущей страницы для каждого игрока
		private Dictionary<ulong, List<Dictionary<string, object>>> _popularItems = new(); // Популярные товары для каждого игрока
		private List<Dictionary<string, object>> _globalPopularItems = null; // Глобальный список популярных товаров (загружается один раз)
		private Dictionary<ulong, double> _lastBasketRequestTime = new(); // Время последнего запроса корзины для каждого игрока
		private Dictionary<ulong, bool> _basketRequestInProgress = new(); // Флаг, что запрос корзины выполняется

		// Кеш данных для раздела помощи (загружается один раз при инициализации)
		private Dictionary<string, object> _helpInfoCache = null; // {wipeInfo: {...}, commands: [...]}

		// Данные поддержки для каждого игрока
		private Dictionary<ulong, string> _playerSupportTickets = new(); // Текущий активный тикет для каждого игрока
		private Dictionary<ulong, List<Dictionary<string, object>>> _playerSupportMessages = new(); // Сообщения поддержки для каждого игрока
		private Dictionary<ulong, string> _playerSupportInputText = new(); // Текст в поле ввода для каждого игрока
		private Dictionary<ulong, bool> _playerSupportInputFocused = new(); // Фокус на поле ввода для каждого игрока
		private Dictionary<ulong, string> _playerSupportLastInputText = new(); // Последний текст для определения нажатия Enter

		private Timer _checkInstant;

		#region Images

		private string NoImageID = string.Empty, LoadingImageID = string.Empty;

		private const string ImageBucket = "GameStoresRUSTBucket";
		private const string ImageCard = "GameStoresRUSTCard";
		private const string ImageCardSuccess = "GameStoresRUSTCardSuccess";
		private const string ImageCardRed = "GameStoresRUSTCardRed";
		private const string ImageCardGray = "GameStoresRUSTCardGray";
		private const string ImageCardPopular = "GameStoresRUSTCardPopular";
		private const string ImageLight = "GameStoresRUSTLight";
		private const string ImageBgRight = "GameStoresRUSTBgRight";
		private const string ImageSpliter = "GameStoresRUSTSpliter";
		private const string ImageMenuPunktBg = "GameStoresRUSTMenuPunktBg";

		private Coroutine _loadingCoroutine;
		private ImageCache _imageCache;
		private bool _imagesCacheInitialized = false;

		private string GetCachedImageId(string imageName)
		{
			// Маппинг имен констант на имена в кеше
			string cacheName = imageName switch
			{
				ImageCard => "card",
				ImageCardSuccess => "card_success",
				ImageCardRed => "card_red",
				ImageCardGray => "card_gray",
				ImageCardPopular => "card_popular",
				ImageLight => "light",
				ImageBgRight => "bg_right",
				ImageSpliter => "spliter",
				ImageMenuPunktBg => "menu_punkt_bg",
				_ => imageName.ToLower()
			};

			if (_imageCache != null && _imageCache.IsImageLoaded(cacheName))
			{
				return _imageCache.GetImageId(cacheName);
			}
			return null;
		}

		#endregion

		#region Web Requests

		private readonly List<Coroutine> _activeCoroutines = new();

		private readonly List<UnityWebRequest> _activeRequests = new();

		private bool SecureConnection = true;

		private Dictionary<string, string> reqHeaders = new()
		{
			["User-Agent"] = "GameStores Plugin"
		};

		private const string
			MainApiLink = "https://api.prostoj.store/v1/game-stores/",
			CFApiLink = "https://api.prostoj.store/v1/game-stores/",
			PayApiLink = "https://api.prostoj.store/v1/";

		private string BestApiLink = string.Empty;

		private int errorsReq;
		private const int MaxErrorsReq = 5;

		#endregion Web Requests

		#endregion

		#region Config

		private Configuration _config;

		private class Configuration
		{
			public class APIConfiguration
			{
				[JsonProperty("Store ID")] public string ShopID = "UNDEFINED";
			}

			public class InterfaceConfiguration
			{
				[JsonProperty("Enable cart image")] public bool BucketEnable = false;

				[JsonProperty("Enable display of item names")]
				public bool TextShow = true;

				[JsonProperty("Link to cart image (BUCKET - default image)")]
				public string BucketURL = "BUCKET";

				[JsonProperty("Number of items per line")]
				public int ItemOnString = 7;

				[JsonProperty("Number of lines in the interface")]
				public int StringAmount = 4;

				[JsonProperty("Side of one item")] public int ItemSide = 99; // Увеличено на 10% (90 * 1.1 = 99)

				[JsonProperty("Space between objects")]
				public int ItemMargin = 5;

				[JsonProperty("Position settings cart image")]
				public InterfacePosition BucketPosition = new()
				{
					AnchorMin = "0 1",
					AnchorMax = "0 1",
					OffsetMin = "8 -40",
					OffsetMax = "43 -6"
				};

				[JsonProperty("Background settings")] public BackgroundSettings Background = new()
				{
					AnchorMin = "0.1 0.1", // Окно по центру, не на весь экран
					AnchorMax = "0.9 0.9",
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					Material = "assets/content/ui/uibackgroundblur.mat",
					Sprite = string.Empty,
					Image = string.Empty,
					Color = IColor.Create("#1A1A1A", 95), // Темно-серый фон как на скриншотах
					DisplayType = "Overlay"
				};

				[JsonProperty("Basket title settings")]
				public ButtonSettings BasketTitle = new()
				{
					AnchorMin = "0.3 0.88",
					AnchorMax = "0.7 0.92",
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					FontSize = 28,
					IsBold = true,
					Align = TextAnchor.MiddleCenter,
					Color = IColor.Create("#FFFFFF", 100), // Белый текст
					ButtonColor = IColor.Create("#000000", 0),
					Sprite = string.Empty,
					Material = string.Empty,
					Image = string.Empty,
					ImageColor = IColor.Create("#FFFFFF")
				};

				[JsonProperty("Basket description settings")]
				public TextSettings BasketDescription = new()
				{
					AnchorMin = "0 0",
					AnchorMax = "1 0",
					OffsetMin = "0 -0",
					OffsetMax = "0 20",
					FontSize = 14,
					IsBold = false,
					Align = TextAnchor.UpperCenter,
					Color = IColor.Create("#FFFFFF", 40)
				};

				[JsonProperty("Help button settings")] public ButtonSettings HelpButton = new()
				{
					AnchorMin = "0.065 0.9",
					AnchorMax = "0.2 1",
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					FontSize = 28,
					IsBold = false,
					Align = TextAnchor.MiddleLeft,
					Color = IColor.Create("#FFFFFF"),
					ButtonColor = IColor.Create("#000000", 0),
					Sprite = string.Empty,
					Material = string.Empty,
					Image = string.Empty,
					ImageColor = IColor.Create("#FFFFFF")
				};

				[JsonProperty(PropertyName = "Block panel settings")]
				public ImageSettings BlockPanel = new()
				{
					AnchorMin = "0 0", // Левая часть модального окна
					AnchorMax = "0.38 0.92", // Узкая панель слева, оставляем место для разделителя и популярных товаров
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					Color = IColor.Create("#080224", 1),
					Sprite = string.Empty,
					Material = string.Empty,
					Image = string.Empty
				};

				[JsonProperty(PropertyName = "Popular items panel settings")]
				public ImageSettings PopularItemsPanel = new()
				{
					AnchorMin = "0.48 0", // После разделителя (разделитель заканчивается на 0.47, небольшой отступ)
					AnchorMax = "1 0.92", // До правого края
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					Color = IColor.Create("#000000", 0), // Прозрачный фон
					Sprite = string.Empty,
					Material = string.Empty,
					Image = string.Empty
				};

				[JsonProperty(PropertyName = "Request processing title settings")]
				public TextSettings RequestProcessingTitle = new()
				{
					AnchorMin = "0 0",
					AnchorMax = "1 1.1",
					OffsetMin = "0 0",
					OffsetMax = "0 0",
					Align = TextAnchor.MiddleCenter,
					Color = IColor.Create("#FFFFFF"),
					IsBold = false,
					FontSize = 34
				};

				[JsonProperty(PropertyName = "Store exit button settings")]
				public ButtonSettings StoreExitButton = new()
				{
					AnchorMin = "0.8 0.9", AnchorMax = "0.935 1", OffsetMin = "0 0", OffsetMax = "0 0",
					ButtonColor = IColor.Create("#000000", 0), // Прозрачный фон
					Material = string.Empty,
					Image = string.Empty,
					Sprite = string.Empty,
					Color = IColor.Create("#FFFFFF"),
					Align = TextAnchor.MiddleRight,
					FontSize = 28,
					IsBold = false,
					ImageColor = IColor.Create("#FFFFFF")
				};

				[JsonProperty(PropertyName = "Item background color")]
				public IColor ItemBackgroundColor = IColor.Create("#2A2A2A", 100); // Темные карточки как на скриншотах

				#region UI.Components

				public class BackgroundSettings : ImageSettings
				{
					[JsonProperty(PropertyName = "Display type (Overlay/Hud)")]
					public string DisplayType;

					public CuiElement Get(string name, string destroyUI, bool needCursor)
					{
						var image = GetImage(DisplayType, name, destroyUI);

						if (needCursor)
							image.Components.Add(new CuiNeedsCursorComponent());

						return image;
					}
				}

				public class ImageSettings : InterfacePosition
				{
					[JsonProperty(PropertyName = "Sprite")]
					public string Sprite;

					[JsonProperty(PropertyName = "Material")]
					public string Material;

					[JsonProperty(PropertyName = "Image")] public string Image;

					[JsonProperty(PropertyName = "Color")] public IColor Color;

					private ICuiComponent GetImage()
					{
						if (!string.IsNullOrEmpty(Image))
						{
							var rawImage = new CuiRawImageComponent
							{
								Png = Instance?.GetImage(Image),
								Color = Color.Get
							};

							if (!string.IsNullOrEmpty(Sprite))
								rawImage.Sprite = Sprite;

							if (!string.IsNullOrEmpty(Material))
								rawImage.Material = Material;

							return rawImage;
						}

						var image = new CuiImageComponent
						{
							Color = Color.Get
						};

						if (!string.IsNullOrEmpty(Sprite))
							image.Sprite = Sprite;

						if (!string.IsNullOrEmpty(Material))
							image.Material = Material;

						return image;
					}

					public CuiElement GetImage(string parent,
						string name = null,
						string destroyUI = null)
					{
						if (string.IsNullOrEmpty(name))
							name = CuiHelper.GetGuid();

						return new CuiElement
						{
							Name = name,
							Parent = parent,
							DestroyUi = destroyUI,
							Components =
							{
								GetImage(),
								GetPosition()
							}
						};
					}
				}

				public class ButtonSettings : TextSettings
				{
					[JsonProperty(PropertyName = "Button Color")]
					public IColor ButtonColor;

					[JsonProperty(PropertyName = "Sprite")]
					public string Sprite;

					[JsonProperty(PropertyName = "Material")]
					public string Material;

					[JsonProperty(PropertyName = "Image")] public string Image;

					[JsonProperty(PropertyName = "Image Color")]
					public IColor ImageColor;

					public List<CuiElement> Get(
						string msg,
						string cmd,
						string parent,
						string name = null,
						string destroyUI = null,
						string close = null)
					{
						if (string.IsNullOrEmpty(name))
							name = CuiHelper.GetGuid();

						var list = new List<CuiElement>();

						var btn = new CuiButtonComponent
						{
							Color = ButtonColor.Get
						};

						if (!string.IsNullOrEmpty(cmd))
							btn.Command = cmd;

						if (!string.IsNullOrEmpty(close))
							btn.Close = close;

						if (!string.IsNullOrEmpty(Sprite))
							btn.Sprite = Sprite;

						if (!string.IsNullOrEmpty(Material))
							btn.Material = Material;

						if (!string.IsNullOrEmpty(Image))
						{
							list.Add(new CuiElement
							{
								Name = name,
								Parent = parent,
								DestroyUi = destroyUI,
								Components =
								{
									new CuiRawImageComponent
									{
										Png = Instance?.GetImage(Image),
										Color = ImageColor.Get
									},
									GetPosition()
								}
							});

							list.Add(new CuiElement
							{
								Parent = name,
								Components =
								{
									btn,
									new CuiRectTransformComponent()
								}
							});
						}
						else
						{
							list.Add(new CuiElement
							{
								Name = name,
								Parent = parent,
								DestroyUi = destroyUI,
								Components =
								{
									btn,
									GetPosition()
								}
							});
						}

						if (!string.IsNullOrEmpty(msg))
							list.Add(new CuiElement
							{
								Parent = name,
								Components =
								{
									GetTextComponent(msg),
									new CuiRectTransformComponent()
								}
							});

						return list;
					}
				}

				public class TextSettings : InterfacePosition
				{
					[JsonProperty(PropertyName = "Font Size")]
					public int FontSize;

					[JsonProperty(PropertyName = "Is Bold?")]
					public bool IsBold;

					[JsonProperty(PropertyName = "Align")] [JsonConverter(typeof(StringEnumConverter))]
					public TextAnchor Align;

					[JsonProperty(PropertyName = "Color")] public IColor Color;

					protected CuiTextComponent GetTextComponent(string msg)
					{
						return new CuiTextComponent
						{
							Text = msg,
							FontSize = FontSize,
							Font = IsBold ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
							Align = Align,
							Color = Color.Get
						};
					}

					public CuiElement GetText(string msg, string parent, string name = null, string destroyUI = null)
					{
						if (string.IsNullOrEmpty(name))
							name = CuiHelper.GetGuid();

						return new CuiElement
						{
							Name = name,
							Parent = parent,
							DestroyUi = destroyUI,
							Components =
							{
								GetTextComponent(msg),
								GetPosition()
							}
						};
					}
				}

				public class InterfacePosition
				{
					public string AnchorMin;

					public string AnchorMax;

					public string OffsetMin;

					public string OffsetMax;

					protected CuiRectTransformComponent GetPosition()
					{
						return new CuiRectTransformComponent
						{
							AnchorMin = AnchorMin,
							AnchorMax = AnchorMax,
							OffsetMin = OffsetMin,
							OffsetMax = OffsetMax
						};
					}
				}

				public class IColor
				{
					[JsonProperty(PropertyName = "Opacity (0 - 100)",
						NullValueHandling = NullValueHandling.Include)]
					public float Alpha;

					[JsonProperty(PropertyName = "HEX", NullValueHandling = NullValueHandling.Include)]
					public string Hex;

					public static IColor Create(string hex, float alpha = 100)
					{
						return new IColor
						{
							Hex = hex,
							Alpha = alpha
						};
					}

					[JsonIgnore] private string _color;

					[JsonIgnore]
					public string Get
					{
						get
						{
							if (string.IsNullOrEmpty(_color))
								UpdateColor();

							return _color;
						}
					}

					public void UpdateColor()
					{
						_color = GetColor();
					}

					private string GetColor()
					{
						if (string.IsNullOrEmpty(Hex)) Hex = "#FFFFFF";

						var str = Hex.Trim('#');
						if (str.Length != 6) throw new Exception(Hex);
						var r = byte.Parse(str.Substring(0, 2), NumberStyles.HexNumber);
						var g = byte.Parse(str.Substring(2, 2), NumberStyles.HexNumber);
						var b = byte.Parse(str.Substring(4, 2), NumberStyles.HexNumber);

						return $"{(double) r / 255} {(double) g / 255} {(double) b / 255} {Alpha / 100}";
					}
				}

				#endregion
			}

			public class PluginConfiguration
			{
				[JsonProperty("Commands to open the store", ObjectCreationHandling = ObjectCreationHandling.Replace)]
				public string[] Commands = {"store", "bucket"};

				[JsonProperty("Enable show joining players in server tags")]
				public bool ShowJoiningPlayers = false;

				[JsonProperty("Load default images over client")]
				public bool LoadSpriteImages = true;

				[JsonProperty("Use Building Blocked?")]
				public bool UseBuildingBlocked = false;

				[JsonProperty("Use Combat Blocked?")] public bool UseCombatBlocked = false;

				[JsonProperty("Use Raid Blocked?")] public bool UseRaidBlocked = false;

				[JsonProperty("Execute instant commands if the player was not on the server?")]
				public bool ExecuteInstantCommandsWhenNotInServer = false;
			}

			[JsonProperty("API configuration")] public APIConfiguration API = new();

			[JsonProperty("Plugin Configuration")] public PluginConfiguration Plugin = new();

			[JsonProperty("UI Configuration")] public InterfaceConfiguration UI = new();

			public VersionNumber ConfigurationVersion = new(2, 0, 0);
		}

		#region Old Config

		private void UpdateConfig(ConfigurationOld _old)
		{
			_config.API.ShopID = _old.API.ShopID;

			_config.UI.BucketEnable = _old.Interface.BucketEnable;
			_config.UI.TextShow = _old.Interface.TextShow;
			_config.UI.BucketURL = _old.Interface.BucketURL;
			_config.UI.ItemOnString = _old.Interface.ItemOnString;
			_config.UI.StringAmount = _old.Interface.StringAmount;
			_config.UI.ItemSide = _old.Interface.ItemSide;
			_config.UI.ItemMargin = _old.Interface.ItemMargin;

			_config.UI.BucketPosition.AnchorMin = _old.Interface.BucketPosition.AnchorMin;
			_config.UI.BucketPosition.AnchorMax = _old.Interface.BucketPosition.AnchorMax;
			_config.UI.BucketPosition.OffsetMin = _old.Interface.BucketPosition.OffsetMin;
			_config.UI.BucketPosition.OffsetMax = _old.Interface.BucketPosition.OffsetMax;
		}

		private class ConfigurationOld
		{
			public class APIConfiguration
			{
				[JsonProperty("ИД магазина в сервисе")]
				public string ShopID = "UNDEFINED";
			}

			public class InterfaceConfiguration
			{
				[JsonProperty("Включить изображение корзины")]
				public bool BucketEnable = true;

				[JsonProperty("Включить отображение названий предметов")]
				public bool TextShow = true;

				[JsonProperty("Ссылка на изображение корзины (BUCKET - стандартное изображение)")]
				public string BucketURL = "BUCKET";

				[JsonProperty("Количество предметов на строке")]
				public int ItemOnString = 7;

				[JsonProperty("Количество строк в интерфейсе")]
				public int StringAmount = 4;

				[JsonProperty("Сторона одного предмета")]
				public int ItemSide = 122;

				[JsonProperty("Отступ между предметами")]
				public int ItemMargin = 5;

				[JsonProperty("Настройки позиции изображение корзины")]
				public Position BucketPosition = new();

				public class Position
				{
					public string AnchorMin = "0 1";
					public string AnchorMax = "0 1";
					public string OffsetMin = "8 -40";
					public string OffsetMax = "43 -6";
				}
			}

			[JsonProperty("Настройки API плагина")]
			public APIConfiguration API = new();

			[JsonProperty("Настройки интерфейса плагина")]
			public InterfaceConfiguration Interface = new();
		}

		#endregion

		protected override void LoadConfig()
		{
			base.LoadConfig();

			try
			{
			// First try to load as new configuration
				_config = Config.ReadObject<Configuration>();
			if (_config?.API != null && _config.API.ShopID != "UNDEFINED")
				{
				// New config loaded successfully
				NextTick(SaveConfig);
				return;
			}

			// If new config is invalid, try to load as old config
			PrintWarning("Old config? Trying to load old configuration format...");

			try
			{
					var oldConfig = Config.ReadObject<ConfigurationOld>();
				if (oldConfig?.API != null && oldConfig.API.ShopID != "UNDEFINED")
					{
					PrintWarning("Old config detected! Updating to new format...");

					_config = new Configuration();
						UpdateConfig(oldConfig);

						NextTick(SaveConfig);
						return;
				}
			}
			catch (Exception oldConfigEx)
			{
				Debug.LogException(oldConfigEx);
					}

			// If both failed, create new default config
			PrintError("Your configuration is invalid! Creating new default configuration...");
					LoadDefaultConfig();
			}
			catch (Exception e)
			{
				LoadDefaultConfig();
				Debug.LogException(e);
			}

			NextTick(SaveConfig);
		}

		protected override void LoadDefaultConfig()
		{
			_config = new Configuration();
		}

		protected override void SaveConfig()
		{
			Config.WriteObject(_config);
		}

		#endregion

		#region Hooks

		#region Init / Unload

		private void Init()
		{
			BestApiLink = MainApiLink;

			Instance = this;
		}

		private void OnServerInitialized()
		{
			// Инициализируем кеш изображений всегда, независимо от ImageLibrary
			InitializeImages();

#if !CARBON
			if (ImageLibrary == null)
			{
				if (TryImageCounter < maxImageCheck)
				{
					TryImageCounter++;

					timer.In(1, OnServerInitialized);
					return;
				}
			}
			else
#endif
			{
				if (_config.UI.BucketURL.Contains("http")
#if !CARBON
				    && ImageLibrary != null
#endif
				   )
					InitializeBucketImage();
				else
					InitializePlayers();
			}

			LoadHeaderParams();

			if (!InitializeServerSettings())
				return;

			RegisterCommands();

			timer.Once(2, FetchShopUrl);

			InitializeTimeoutCommands();

			// Загружаем данные для раздела помощи один раз при инициализации
			LoadHelpInfo();

			// Загружаем популярные товары один раз при инициализации (для всех игроков)
			LoadPopularItems();

			// Выдаем права notify.see всем текущим игрокам для отображения уведомлений
			if (Notify != null && Notify.IsLoaded)
			{
				foreach (var player in BasePlayer.activePlayerList)
				{
					permission.GrantUserPermission(player.UserIDString, "notify.see", null);
				}
			}
		}

		private void Unload()
		{
			try
			{
				DestroyInstantTimer();

				if (_loadingCoroutine != null) ServerMgr.Instance.StopCoroutine(_loadingCoroutine);

				// Останавливаем все загрузки изображений в ImageCache
				if (_imageCache != null)
				{
					_imageCache.StopAllDownloads();
				}

				foreach (var player in BasePlayer.activePlayerList)
				{
					// Удаляем все UI элементы плагина
					CuiHelper.DestroyUi(player, IconLayer);

					// Удаляем модальное окно и все его дочерние элементы
					CuiHelper.DestroyUi(player, StoreLayer + ".Window");
					CuiHelper.DestroyUi(player, StoreLayer + ".Blur");

					// Удаляем все остальные элементы (на случай, если что-то осталось)
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");
					CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel");
					CuiHelper.DestroyUi(player, StoreLayer + ".PopularItemsPanel");
					CuiHelper.DestroyUi(player, StoreLayer + ".TopNav");
					CuiHelper.DestroyUi(player, StoreLayer + ".Notify");

					// Удаляем основной слой (это удалит все дочерние элементы)
					CuiHelper.DestroyUi(player, StoreLayer);
				}

				DestroyRequestCoroutines();

				DisposeActiveRequests();
			}
			finally
			{
				Instance = null;
			}
		}

		#endregion

		#region Player

		private void OnPlayerConnected(BasePlayer player)
		{
			if (player == null) return;

			// Выдаем права notify.see для отображения уведомлений
			if (Notify != null && Notify.IsLoaded)
			{
				permission.GrantUserPermission(player.UserIDString, "notify.see", null);
			}

			if (_config.UI.BucketEnable)
				InitializeIcon(player);
		}

		private void OnPlayerDisconnected(BasePlayer player)
		{
			if (player == null) return;

			DequeueBasketRequest(player.userID);

			// Очищаем данные поддержки
			_playerSupportTickets.Remove(player.userID);
			_playerSupportMessages.Remove(player.userID);
			_playerSupportInputText.Remove(player.userID);
			_playerSupportInputFocused.Remove(player.userID);
			_playerSupportLastInputText.Remove(player.userID);
			_popularItems.Remove(player.userID);
			_lastBasketRequestTime.Remove(player.userID);
			_basketRequestInProgress.Remove(player.userID);
		}

		#endregion

		#region Server

		private void OnServerInformationUpdated()
		{
			if (!_config.Plugin.ShowJoiningPlayers)
			{
				Unsubscribe(nameof(OnServerInformationUpdated));
				return;
			}

			NextTick(() =>
			{
				if (SteamServer.GameTags.Length > 122)
				{
					Unsubscribe(nameof(OnServerInformationUpdated));
					return;
				}

				var newTags = new List<string>();
				foreach (var tag in SteamServer.GameTags.Split(new[] {','}, StringSplitOptions.RemoveEmptyEntries))
				{
					newTags.Add(tag);

					if (tag.Substring(0, 2) == "qp")
						newTags.Add($"jp{SingletonComponent<ServerMgr>.Instance.connectionQueue.Joining}");
				}

				try
				{
					SteamServer.GameTags = string.Join(",", newTags);
				}
				catch (Exception ex)
				{
					Unsubscribe(nameof(OnServerInformationUpdated));
				}
			});
		}

		#endregion

		#endregion

		#region Commands

		private void CmdChatStore(IPlayer cov, string command, string[] args)
		{
			var player = cov?.Object as BasePlayer;
			if (player == null || player.Connection == null || player.IsSleeping())
				return;

			if (maintenance)
			{
				Reply(player, Maintenance);
				return;
			}

			if (!Initialized)
			{
				HandleInitializationError(player);
				return;
			}

			if (args.Length == 1 && args[0].ToLower() == "hide")
			{
				CuiHelper.DestroyUi(player, IconLayer);
				return;
			}


			if (CanRequestBasket(player))
				InitializeStore(player, 0);
		}

		private void CmdChatHelp(IPlayer cov, string command, string[] args)
		{
			var player = cov?.Object as BasePlayer;
			if (player == null || player.Connection == null || player.IsSleeping())
				return;

			if (maintenance)
			{
				Reply(player, Maintenance);
				return;
			}

			if (!Initialized)
			{
				HandleInitializationError(player);
				return;
			}

			// Проверяем, открыт ли уже интерфейс корзины
			// Если нет, открываем его сначала, затем переключаемся на помощь
			if (!_playerBaskets.ContainsKey(player.userID))
			{
				if (CanRequestBasket(player))
				{
					_playerPages[player.userID] = 0;
					InitializeStore(player, 0);
					// Открываем помощь после небольшой задержки, чтобы интерфейс успел загрузиться
					timer.Once(0.3f, () => ShowHelpUI(player));
					return;
				}
			}
			else
			{
				// Если интерфейс уже открыт, просто переключаемся на вкладку помощи
				ShowHelpUI(player);
			}
		}

		// RCON команда для уведомления игрока о новом сообщении в тикете поддержки
		// Использование: support.notify <steamId> <message>
		// Вызывается с сервера через RCON, когда модератор/администратор отвечает в тикете
		// Формат RCON команды: support.notify 76561198394504608 "Ваш вопрос был рассмотрен"
		[ConsoleCommand("support.notify")]
		private void CmdSupportNotify(ConsoleSystem.Arg args)
		{
			if (args.Args == null || args.Args.Length < 2)
			{
				args.ReplyWith("Usage: support.notify <steamId> <message>");
				return;
			}

			string steamIdStr = args.Args[0];
			string message = string.Join(" ", args.Args.Skip(1));

			if (string.IsNullOrWhiteSpace(steamIdStr) || string.IsNullOrWhiteSpace(message))
			{
				args.ReplyWith("Error: steamId and message are required");
				return;
			}

			// Парсим Steam ID
			if (!ulong.TryParse(steamIdStr, out ulong steamId))
			{
				args.ReplyWith($"Error: Invalid Steam ID format: {steamIdStr}");
				return;
			}

			// Ищем игрока по Steam ID
			BasePlayer player = BasePlayer.FindByID(steamId);
			if (player == null || !player.IsConnected)
			{
				args.ReplyWith($"Error: Player with Steam ID {steamId} not found or not connected");
				return;
			}

			// Отправляем сообщение в чат со звуком
			// Формат: chat.add <channel> <steamId> <message>
			// channel: 0 = Global, 1 = Team, 2 = Clan
			// steamId: 0 = системное сообщение (со звуком), можно использовать реальный Steam ID для аватара
			// Для звука используем steamId = 0 (системное сообщение со звуком уведомления)
			player.SendConsoleCommand("chat.add", 0, 0, $"<color=#FFA500>[Поддержка]</color> {message}");

			// Также отправляем уведомление через ShowNotify для более заметного отображения
			ShowNotify(player, $"Новое сообщение в поддержке: {message}", false);

			args.ReplyWith($"Notification sent to player {player.displayName} (Steam ID: {steamId})");
			PrintWarning($"[Support] RCON notification sent to player {player.displayName} (Steam ID: {steamId}): {message}");
		}

		[ConsoleCommand("UI_GameStoresRUST")]
		private void CmdConsoleCommand(ConsoleSystem.Arg args)
		{
			var player = args.Player();
			if (player == null) return;

			if (HandleCommandBan(player, args)) return;

			switch (args.Args[0].ToLower())
			{
				case "page":
				{
					// Переход между страницами только через пагинацию в меню
					var page = args.GetInt(1);
					_playerPages[player.userID] = page; // Сохраняем текущую страницу

					// Обновляем только содержимое корзины и пагинацию, не пересоздавая весь интерфейс
					if (_playerBaskets.TryGetValue(player.userID, out var playerBasket))
					{
						ShowBasketItemsUI(player, page);
					}
					else
					{
						// Если корзина не загружена, запрашиваем её
						RequestBasket(player, page);
					}
					break;
				}

				case "help":
				{
					ShowHelpUI(player);
					break;
				}

				case "support":
				{
					ShowSupportUI(player);
					break;
				}

				case "support.refresh":
				{
					LoadSupportMessages(player);
					break;
				}

				case "support.input":
				{
					// Сохраняем текст из InputField (вызывается при изменении или нажатии Enter)
					string messageText = "";

					// В Rust Oxide InputField передает текст как первый аргумент после команды
					if (args.Args.Length > 1)
					{
						// Объединяем все аргументы в один текст (на случай пробелов)
						messageText = string.Join(" ", args.Args.Skip(1));
					}

					// Сохраняем текст
					_playerSupportInputText[player.userID] = messageText;

					// Проверяем, пусто ли поле
					if (string.IsNullOrWhiteSpace(messageText))
					{
						// Поле пустое - показываем label и возвращаем исходный цвет фона
						// Проверяем, был ли label скрыт (поле было в фокусе)
						if (_playerSupportInputFocused.TryGetValue(player.userID, out var wasFocused) && wasFocused)
						{
							// Показываем label обратно
							var labelContainer = new CuiElementContainer();
							labelContainer.Add(new CuiButton
							{
								RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.58 1", OffsetMin = "0 0", OffsetMax = "0 0" },
								Button = { Color = "0 0 0 0", Command = "UI_GameStoresRUST support.inputfocus", Close = "" },
								Text = { Text = "Введите сообщение", FontSize = 12, Align = TextAnchor.MiddleLeft, Font = "RobotoCondensed-Bold.ttf", Color = "0.6 0.6 0.6 1" },
							}, StoreLayer + ".SupportPanel.InputPanel", StoreLayer + ".SupportPanel.InputLabel");
							CuiHelper.AddUi(player, labelContainer);

							// Возвращаем исходный цвет фона
							var bgContainer = new CuiElementContainer();
							bgContainer.Add(new CuiElement
							{
								Name = StoreLayer + ".SupportPanel.InputFieldBg",
								Parent = StoreLayer + ".SupportPanel.InputPanel",
								Components =
								{
									new CuiImageComponent { Color = "0.098 0.063 0.176 1" }, // Исходный цвет #19102d
									new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0.592 1", OffsetMin = "0 0", OffsetMax = "0 0" },
								}
							});
							CuiHelper.AddUi(player, bgContainer);

							_playerSupportInputFocused[player.userID] = false;
						}
					}
					else
					{
						// Есть текст - скрываем label и меняем цвет фона
						_playerSupportInputFocused[player.userID] = true;
						CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.InputLabel");

						// Меняем цвет фона на более светлый для индикации фокуса
						var focusContainer = new CuiElementContainer();
						focusContainer.Add(new CuiElement
						{
							Name = StoreLayer + ".SupportPanel.InputFieldBg",
							Parent = StoreLayer + ".SupportPanel.InputPanel",
							Components =
							{
								new CuiImageComponent { Color = "0.12 0.08 0.22 1" }, // Немного светлее для индикации фокуса
								new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0.592 1", OffsetMin = "0 0", OffsetMax = "0 0" },
							}
						});
						CuiHelper.AddUi(player, focusContainer);

						// Проверяем, был ли текст уже сохранен ранее (это может означать нажатие Enter)
						// Если предыдущий текст совпадает с текущим, значит пользователь нажал Enter для отправки
						if (_playerSupportLastInputText.TryGetValue(player.userID, out var lastText) &&
						    lastText == messageText &&
						    !string.IsNullOrWhiteSpace(messageText))
						{
							// Текст не изменился с последнего вызова, значит это нажатие Enter - отправляем сообщение
							PrintWarning($"[Support] Enter pressed, sending message: '{messageText}'");
							SendSupportMessage(player, messageText);
							// Очищаем сохраненные тексты
							_playerSupportInputText.Remove(player.userID);
							_playerSupportLastInputText.Remove(player.userID);
							return; // Выходим, чтобы не продолжать обработку
						}

						// Сохраняем текущий текст как последний для следующей проверки
						_playerSupportLastInputText[player.userID] = messageText;
					}

					PrintWarning($"[Support] Input text: '{messageText}' (length: {messageText?.Length ?? 0})");
					break;
				}

				case "support.inputfocus":
				{
					// Обработчик клика по label - скрываем label и фокусируем поле
					CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.InputLabel");
					_playerSupportInputFocused[player.userID] = true;

					// Изменяем цвет фона поля ввода для визуальной индикации фокуса
					var focusContainer = new CuiElementContainer();
					focusContainer.Add(new CuiElement
					{
						Name = StoreLayer + ".SupportPanel.InputFieldBg",
						Parent = StoreLayer + ".SupportPanel.InputPanel",
						Components =
						{
							new CuiImageComponent { Color = "0.12 0.08 0.22 1" }, // Немного светлее для индикации фокуса
							new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0.592 1", OffsetMin = "0 0", OffsetMax = "0 0" },
						}
					});
					CuiHelper.AddUi(player, focusContainer);
					break;
				}

				case "support.send":
				{
					// Получаем текст из аргументов команды (если передан)
					if (args.Args.Length > 1)
					{
						string messageText = string.Join(" ", args.Args.Skip(1));
						if (!string.IsNullOrWhiteSpace(messageText))
						{
							SendSupportMessage(player, messageText);
						}
						else
						{
							ShowNotify(player, "Введите текст сообщения", true);
						}
					}
					else
					{
						ShowNotify(player, "Введите текст сообщения", true);
					}
					break;
				}

				case "support.sendclick":
				{
					// Клик по кнопке отправки - используем сохраненный текст
					if (_playerSupportInputText.TryGetValue(player.userID, out var messageText) && !string.IsNullOrWhiteSpace(messageText))
					{
						SendSupportMessage(player, messageText);
						_playerSupportInputText.Remove(player.userID);
					}
					else
					{
						ShowNotify(player, "Введите текст сообщения в поле ввода", true);
					}
					break;
				}

				case "back":
				{
					// Возврат к корзине из помощи или поддержки
					var page = _playerPages.TryGetValue(player.userID, out var p) ? p : 0;

					// Обновляем навигационное меню - делаем активной кнопку "Моя корзина"
					UpdateNavigationBar(player, "basket");

					// ВАЖНО: Удаляем HelpPanel и SupportPanel ПЕРВЫМИ, так как они занимают весь экран и могут перехватывать клики
					// Удаляем все дочерние элементы HelpPanel явно ПЕРЕД удалением самой панели
					// Удаляем все элементы HelpPanel в правильном порядке (сначала дочерние, потом родительские)

					// Удаляем команды и их дочерние элементы (до 50)
					for (int i = 0; i < 50; i++)
					{
						CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}.Desc");
						CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}.Cmd");
						CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}");
					}

					// Удаляем элементы NextWipeBox
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.GlobalBadge");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Content");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Text");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox");

					// Удаляем элементы LastWipeBox
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.LastWipeBox.Text");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.LastWipeBox");

					// Удаляем другие элементы HelpPanel
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.OnlineBox");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsTitle");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsList");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.Instruction");
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.BackButton");
					CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Help");

					// Удаляем саму панель помощи (это должно удалить все дочерние элементы автоматически)
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");

					// Удаляем HelpPanel еще раз для надежности
					CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");

					// Удаляем панель поддержки
					CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel");

					// Удаляем SupportPanel еще раз для надежности
					CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel");

					// Добавляем небольшую задержку перед восстановлением корзины, чтобы убедиться, что HelpPanel полностью удален
					timer.Once(0.15f, () =>
					{
						// ВАЖНО: Восстанавливаем панели в правильном порядке, чтобы обеспечить правильный z-order
						// В Rust UI порядок элементов в контейнере определяет z-order - элементы, добавленные позже, отображаются поверх

						// Удаляем HelpPanel и все его дочерние элементы еще раз для надежности (на случай, если они не были полностью удалены)
						// Удаляем все элементы HelpPanel в правильном порядке (сначала дочерние, потом родительские)
						for (int i = 0; i < 50; i++)
						{
							CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}.Desc");
							CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}.Cmd");
							CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}");
						}
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.GlobalBadge");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Content");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Text");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.LastWipeBox.Text");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.LastWipeBox");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.OnlineBox");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsTitle");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsList");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.Instruction");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.BackButton");
						CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Help");
						CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");
						CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel");

						// ВАЖНО: Восстанавливаем панели точно так же, как при первоначальном открытии через /store
						// Используем CreateBlockPanel для правильного порядка создания панелей
						var restoreContainer = new CuiElementContainer();

						// Удаляем старые панели, если они существуют (на случай, если они были созданы неправильно)
						CuiHelper.DestroyUi(player, StoreLayer + ".PopularItemsPanel");
						CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel");

						// Создаем панели заново в правильном порядке, как в CreateBlockPanel
						// Сначала PopularItemsPanel (чтобы была внизу по z-order)
						restoreContainer.Add(new CuiPanel
						{
							RectTransform =
							{
								AnchorMin = _config.UI.PopularItemsPanel.AnchorMin,
								AnchorMax = _config.UI.PopularItemsPanel.AnchorMax,
								OffsetMin = _config.UI.PopularItemsPanel.OffsetMin,
								OffsetMax = _config.UI.PopularItemsPanel.OffsetMax
							},
							Image = {Color = "0 0 0 0"} // Прозрачный фон
						}, StoreLayer + ".Window", StoreLayer + ".PopularItemsPanel");

						// Затем BlockPanel (чтобы была поверх PopularItemsPanel)
						restoreContainer.Add(new CuiPanel
						{
							RectTransform =
							{
								AnchorMin = _config.UI.BlockPanel.AnchorMin,
								AnchorMax = _config.UI.BlockPanel.AnchorMax,
								OffsetMin = _config.UI.BlockPanel.OffsetMin,
								OffsetMax = _config.UI.BlockPanel.OffsetMax
							},
							Image = {Color = "0 0 0 0"} // Прозрачный фон
						}, StoreLayer + ".Window", StoreLayer + ".BlockPanel");

						// Заголовок для товаров корзины
						restoreContainer.Add(new CuiLabel
						{
							RectTransform = {AnchorMin = "0 0.92", AnchorMax = "0 0.98", OffsetMin = "10 0", OffsetMax = "200 0"},
							Text = {Text = "МОИ ТОВАРЫ", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "1 0.38 0.204 1"}
						}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Title");

						// Заголовок для популярных товаров
						restoreContainer.Add(new CuiLabel
						{
							RectTransform = {AnchorMin = "1 0.92", AnchorMax = "1 0.98", OffsetMin = "-200 0", OffsetMax = "-10 0"},
							Text = {Text = "МОМЕНТАЛЬНАЯ ПОКУПКА", Align = TextAnchor.MiddleRight, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "1 0.38 0.204 1"}
						}, StoreLayer + ".PopularItemsPanel", StoreLayer + ".PopularItemsPanel.Title");

						// Добавляем восстановленные панели
						CuiHelper.AddUi(player, restoreContainer);

						// Сначала показываем популярные товары (они будут внизу по z-order)
						ShowPopularItemsUI(player);

						// Затем восстанавливаем корзину с товарами и пагинацией (карточки корзины будут поверх популярных товаров)
						if (_playerBaskets.TryGetValue(player.userID, out var playerBasket))
						{
							ShowBasketItemsUI(player, page);
						}
						else
						{
							// Если корзина не загружена, запрашиваем её
							RequestBasket(player, page);
						}
					});
					break;
				}

				case "take":
				{
					if (!args.HasArgs(3)) return;

					if (!int.TryParse(args.Args[1], out var index) ||
					    !int.TryParse(args.Args[2], out var basketID)) return;

					ProcessTakeCommand(player, index, basketID);
					break;
				}

				case "buypopular":
				{
					if (!args.HasArgs(1)) return;

					if (!int.TryParse(args.Args[1], out var dropId)) return;

					ProcessBuyPopularItem(player, dropId);
					break;
				}
			}
		}

		[ConsoleCommand("closemenu")]
		private void CmdCloseMenu(ConsoleSystem.Arg args)
		{
			var player = args.Player();
			if (player == null) return;

			// Удаляем blur фон и основное окно
			CuiHelper.DestroyUi(player, StoreLayer + ".Blur");
			CuiHelper.DestroyUi(player, StoreLayer + ".Window");
			CuiHelper.DestroyUi(player, StoreLayer);

			DequeueBasketRequest(player.userID);
		}

		[ConsoleCommand("store.take")]
		private void CmdStoreTake(ConsoleSystem.Arg args)
		{
			if (!args.IsAdmin || args.IsClientside)
			{
				args.ReplyWith("{\"success\": false, \"error\": \"Не админ или клиентский вызов\"}");
				return;
			}

			ulong steam_id = 0;
			int id = 0;
			bool isBlockedBuilding = false;
			if (args.HasArgs(3) && args.Args[2] == "true")
			{
				isBlockedBuilding = true;
			}
			if (!ulong.TryParse(args.Args[0], out steam_id) || !int.TryParse(args.Args[1], out id))
			{
				args.ReplyWith("{\"success\": false, \"error\": \"Ошибка выполнения запроса, обратитесь в тех. поддержку\"}");
				return;
			}

			var player = BasePlayer.FindByID(steam_id);
			if (player == null)
			{
				args.ReplyWith("{\"success\": false, \"error\": \"Игрок не найден или не в сети. Пожалуйста подключитесь к серверу\"}");
				return;
			}

			if (player.IsDead())
			{
				args.ReplyWith("{\"success\": false, \"error\": \"Игрок мертв, возродитесь чтобы вывести предмет\"}");
				return;
			}

			if (ListBannedCommandUserID.Contains(player.userID))
			{
				player.SendConsoleCommand("chat.add", 0, 76561198394504608, Msg(player, MsgPlayerFloodBlock));
				args.ReplyWith("{\"success\": false, \"error\": \"Игрок заблокирован за флуд, нельзя выполнять много запросов подряд. Подождите 30 секунд.\"}");
				return;
			}

			if (isBlockedBuilding && IsBlockedByForeignTCNearFoundationOrCeiling(player))
			{
				// Игрок в зоне чужого шкафа без авторизации
				args.ReplyWith("{\"success\": false, \"error\": \"Вывод предметов в зоне чужого шкафа без авторизации запрещен!\"}");
				return;
			}

			// Логика с ListTimeOutCommand (если нужно, можно добавить в ответ)
			// ...

			args.ReplyWith("{\"success\": true, \"message\": \"Предмет успешно выдан\"}");
			Request("baskets.item", new Dictionary<string, string>
			{
				{"basketId", id.ToString()}
			}, (i, s) =>
			{
				switch (i)
				{
					case 0:
						break;
					case 200:
						var response = JsonConvert.DeserializeObject<Dictionary<string, object>>(s, new KeyValuesConverter());
						if (response == null || !response.ContainsKey("data"))
						{
							return;
						}

						Request("baskets.makeIssued", new Dictionary<string, string>
						{
							{"steamId", player.UserIDString},
							{"basketId", id.ToString()}
						}, (code, newResponse) =>
						{
							if (code != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() != "success")
							{
								return;
							}

							ProcessTake(player, response["data"] as Dictionary<string, object>);
						}, player);
						break;
					case 404:
						break;
					default:
						break;
				}
			}, player);
		}

		#endregion

		#region Interface

		private const string
			StoreLayer = "UI_GameStoresRUST_Store",
			IconLayer = "UI_GameStoresRUST_Bucket",
			HelpLayer = StoreLayer + ".Help";

		private void InitializeStore(BasePlayer player, int page, bool first = true)
		{
			var container = new CuiElementContainer();

			if (first)
				CreateStoreUiContainer(ref container, player);

			CreateBlockPanel(ref container, player);

			// Кнопка выхода теперь в навигационной панели (крестик)

			// Нижняя граница меню (border-bottom) - 2px под меню (добавляем последним, чтобы было поверх всех элементов)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0.92", AnchorMax = "1 0.92", OffsetMin = "0 -2", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон, так как modal_background уже используется
			}, StoreLayer + ".Window", StoreLayer + ".TopNavBorder");

			CuiHelper.AddUi(player, container);

			_playerPages[player.userID] = page; // Сохраняем текущую страницу

			if (first)
			{
				RequestBasket(player, page);
				// Популярные товары уже загружены при инициализации, просто показываем их
				ShowPopularItemsUI(player);
			}
			else
			{
				// Сначала показываем популярные товары (они будут внизу по z-order)
				ShowPopularItemsUI(player);
				// Затем показываем карточки корзины (они будут поверх популярных товаров)
				ShowBasketItemsUI(player, page);
			}
		}

		/// <summary>
		/// Показывает уведомление через API плагина Notify
		/// </summary>
		/// <param name="player">Игрок</param>
		/// <param name="text">Текст уведомления</param>
		/// <param name="isError">Является ли это ошибкой (type 1) или обычным уведомлением (type 0)</param>
		private void ShowNotify(BasePlayer player, string text, bool isError = false)
		{
			if (player == null || !player.IsConnected) return;

			// Используем API плагина Notify
			// type 0 - обычное уведомление, type 1 - ошибка
			int notifyType = isError ? 1 : 0;
			Notify?.Call("SendNotify", player, notifyType, text);
		}

		private void ShowHelpUI(BasePlayer player)
		{
			// Обновляем навигационное меню - делаем активной кнопку "Помощь"
			UpdateNavigationBar(player, "help");

			// Удаляем панель поддержки, если она была открыта
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel");

			// Удаляем только содержимое блока с товарами (все дочерние элементы), но оставляем сам BlockPanel
			// Удаляем все элементы внутри BlockPanel
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Text");
			// Удаляем заголовок "МОИ ТОВАРЫ"
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Title");
			// Удаляем все карточки товаров
			for (int i = 0; i < basketItemsPerPage; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{i}");
			}
			// Удаляем пагинацию
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Text");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Prev");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Next");
			// Удаляем старую панель помощи, если она есть
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Help");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");
			// Удаляем панель поддержки, если она была открыта
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel");
			// Удаляем популярные товары при переходе в помощь
			for (int i = 0; i < 8; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".PopularItemsPanel.Item{i}");
			}
			// Скрываем панель популярных товаров
			CuiHelper.DestroyUi(player, StoreLayer + ".PopularItemsPanel");
			// НЕ удаляем bg_right.png - он должен оставаться видимым в правом нижнем углу модального окна

			var container = new CuiElementContainer();

			// Фон для блока помощи - создаем панель, которая занимает место от навигации (0.92) до низа
			// ВАЖНО: Используем CuiElement вместо CuiPanel, чтобы панель не перехватывала клики
			// Прозрачный фон не нужен, так как modal_background уже используется для всего окна
			container.Add(new CuiElement
			{
				Parent = StoreLayer + ".Window",
				Name = StoreLayer + ".HelpPanel",
				Components =
				{
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 0.92",
						OffsetMax = "0 0"
					}
					// НЕ добавляем Image компонент, чтобы панель не перехватывала клики
				}
			});

			// Получаем информацию о сервере
			int playerCount = BasePlayer.activePlayerList.Count;
			int maxPlayers = ConVar.Server.maxplayers;
			string mapName = ConVar.Server.level;

			// Получаем время вайпа (если доступно)
			string wipeTime = "НЕИЗВЕСТНО";
			try
			{
				// Попытка получить время вайпа через GameStoresWipeBlock
				if (GameStoresWipeBlock != null)
				{
					var wipeDate = GameStoresWipeBlock.Call("GetWipeDate");
					if (wipeDate != null)
					{
						var timeSinceWipe = DateTime.Now - ((DateTime)wipeDate);
						if (timeSinceWipe.TotalHours < 24)
							wipeTime = $"{(int)timeSinceWipe.TotalHours} ЧАСОВ С ВАЙПА";
						else
							wipeTime = $"{(int)timeSinceWipe.TotalDays} ДНЕЙ С ВАЙПА";
					}
				}
			}
			catch
			{
				// Если не удалось получить время вайпа, используем значение по умолчанию
			}

			// Верхняя секция с информацией о сервере (4 колонки)
			// Колонка 1: ОНЛАЙН
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.05 0.88", AnchorMax = "0.29 0.92", OffsetMax = "0 0"},
				Text = {Text = "ОНЛАЙН", Align = TextAnchor.UpperLeft, Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "0.7 0.7 0.7 1"}
			}, StoreLayer + ".HelpPanel");
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0.05 0.82", AnchorMax = "0.29 0.87", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.OnlineBox");
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Text = {Text = $"{playerCount}/{maxPlayers}", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel.OnlineBox");

			// Колонка 2: КОГДА БЫЛ ВАЙП? (будет обновлено из API)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.28 0.88", AnchorMax = "0.52 0.92", OffsetMax = "0 0"},
				Text = {Text = "КОГДА БЫЛ ВАЙП?", Align = TextAnchor.UpperLeft, Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "0.7 0.7 0.7 1"}
			}, StoreLayer + ".HelpPanel");
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0.28 0.82", AnchorMax = "0.52 0.87", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.LastWipeBox");
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Text = {Text = "Загрузка...", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel.LastWipeBox", StoreLayer + ".HelpPanel.LastWipeBox.Text");

			// Колонка 3: КОГДА БУДЕТ ВАЙП? (будет обновлено из API)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.51 0.88", AnchorMax = "0.75 0.92", OffsetMax = "0 0"},
				Text = {Text = "КОГДА БУДЕТ ВАЙП?", Align = TextAnchor.UpperLeft, Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "0.7 0.7 0.7 1"}
			}, StoreLayer + ".HelpPanel");
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0.51 0.82", AnchorMax = "0.75 0.87", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.NextWipeBox");
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Text = {Text = "Загрузка...", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel.NextWipeBox", StoreLayer + ".HelpPanel.NextWipeBox.Text");

			// Нижняя секция со списком информации (будет обновлено из API)
			// Показываем заглушку с таким же отступом как блок "ОНЛАЙН"
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.05 0.5", AnchorMax = "0.9 0.75", OffsetMax = "0 0"},
				Text = {Text = "Загрузка информации...", Align = TextAnchor.MiddleCenter, Font = "robotocondensed-regular.ttf", FontSize = 16, Color = "0.7 0.7 0.7 1"}
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.CommandsList");

			// Кнопка "Перейти в корзину" - по левому краю с таким же отступом как блок "ОНЛАЙН", с нормальными внутренними отступами
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0.05 0.05", AnchorMax = "0.35 0.12", OffsetMin = "0 0", OffsetMax = "0 0"},
				Button = {Color = "0.9 0.3 0.1 1", Command = "UI_GameStoresRUST back"},
				Text =
				{
					Text = "Перейти в корзину", Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1",
					FadeIn = 0f
				}
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.BackButton");

			// Добавляем текст с отступами внутри кнопки (отступы слева и справа уменьшены в 3 раза)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "1 0", OffsetMax = "-1 0"},
				Text =
				{
					Text = "Перейти в корзину", Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"
				}
			}, StoreLayer + ".HelpPanel.BackButton");

			CuiHelper.AddUi(player, container);

			// Используем кешированные данные вместо запроса к API
			if (_helpInfoCache != null)
			{
				ApplyHelpInfoToUI(player);
			}

			// Обновляем навигационное меню - делаем активной кнопку "Помощь"
			UpdateNavigationBar(player, "help");
		}

		private void ShowSupportUI(BasePlayer player)
		{
			PrintWarning($"[Support] ShowSupportUI called for player {player.UserIDString}");

			// Удаляем содержимое других разделов
			// Удаляем только содержимое блока с товарами (все дочерние элементы), но оставляем сам BlockPanel
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Text");
			// Удаляем заголовок "МОИ ТОВАРЫ"
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Title");
			// Удаляем все карточки товаров
			for (int i = 0; i < basketItemsPerPage; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{i}");
			}
			// Удаляем пагинацию
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Text");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Prev");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Next");
			// Удаляем старую панель помощи, если она есть
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Help");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel");
			// Удаляем популярные товары при переходе в поддержку
			for (int i = 0; i < 8; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".PopularItemsPanel.Item{i}");
			}
			// Скрываем панель популярных товаров
			CuiHelper.DestroyUi(player, StoreLayer + ".PopularItemsPanel");
			// НЕ удаляем bg_right.png - он должен оставаться видимым в правом нижнем углу модального окна

			var container = new CuiElementContainer();

			// Фон для блока поддержки - создаем панель, которая занимает место от навигации (0.92) до низа
			container.Add(new CuiElement
			{
				Parent = StoreLayer + ".Window",
				Name = StoreLayer + ".SupportPanel",
				Components =
				{
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 0.92",
						OffsetMax = "0 0"
					}
				}
			});

			// Основная панель для содержимого поддержки (с таким же отступом слева как у команд в разделе помощи - 0.05)
			// Начинается выше, с небольшим отступом от верхнего меню, как в разделе Корзина сервера
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.Content",
				Parent = StoreLayer + ".SupportPanel",
				Components =
				{
					new CuiImageComponent { Color = "1 1 1 0" },
					// AnchorMin: начинаем с 0.0507 от низа панели
					// AnchorMax: заканчиваем на 0.9484 от низа панели
					new CuiRectTransformComponent { AnchorMin = "0.05 0.0507", AnchorMax = "0.95 0.9484", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			// Панель для ввода сообщения и кнопок
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputPanel",
				Parent = StoreLayer + ".SupportPanel.Content",
				Components =
				{
					new CuiImageComponent { Color = "1 1 1 0" },
					new CuiRectTransformComponent { AnchorMin = "-0.0004 0.0006", AnchorMax = "0.5443 0.0817", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			// Поле ввода сообщения с фоном
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputFieldBg",
				Parent = StoreLayer + ".SupportPanel.InputPanel",
				Components =
				{
					new CuiImageComponent { Color = "0.098 0.063 0.176 1" }, // #19102d
					new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0.592 1", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			// Label "Введите сообщение" (показывается только когда поле пустое)
			// Делаем label кликабельным, чтобы при клике скрывать его и фокусировать поле
			container.Add(new CuiButton
			{
				RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.58 1", OffsetMin = "0 0", OffsetMax = "0 0" },
				Button = { Color = "0 0 0 0", Command = "UI_GameStoresRUST support.inputfocus", Close = "" },
				Text = { Text = "Введите сообщение", FontSize = 12, Align = TextAnchor.MiddleLeft, Font = "RobotoCondensed-Bold.ttf", Color = "0.6 0.6 0.6 1" },
			}, StoreLayer + ".SupportPanel.InputPanel", StoreLayer + ".SupportPanel.InputLabel");

			// Панель для поля ввода (как в других плагинах)
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputField",
				Parent = StoreLayer + ".SupportPanel.InputPanel",
				Components =
				{
					new CuiImageComponent { Color = "0 0 0 0" },
					new CuiRectTransformComponent { AnchorMin = "-0.0044 0", AnchorMax = "0.592 1", OffsetMin = "15 0", OffsetMax = "-15 0" },
				}
			});

			// Вложенный элемент с InputFieldComponent (как в других плагинах)
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputField.Current",
				Parent = StoreLayer + ".SupportPanel.InputField",
				Components =
				{
					new CuiInputFieldComponent
					{
						Text = "",
						Font = "RobotoCondensed-Bold.ttf",
						FontSize = 14,
						Align = TextAnchor.MiddleLeft,
						Color = "0.925 0.894 0.953 1",
						Command = "UI_GameStoresRUST support.input",
						CharsLimit = 500,
						NeedsKeyboard = true,
					},
					new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1" },
				}
			});

			// Прозрачная кнопка поверх поля ввода для обработки клика (скрывает label при фокусе)
			// Используем z-index ниже, чем у InputField, чтобы не блокировать ввод
			// Но это не сработает, так как кнопка все равно будет поверх
			// Вместо этого полагаемся на обработчик support.input, который вызывается при вводе
			// И на обработчик support.inputfocus при клике на label

			// Кнопка отправки
			container.Add(new CuiButton
			{
				RectTransform = { AnchorMin = "0.6096 0", AnchorMax = "0.8761 1", OffsetMin = "0 0", OffsetMax = "0 0" },
				Button = { Color = "0.949 0.302 0.302 1", Command = "UI_GameStoresRUST support.sendclick", Close = "" },
				Text = { Text = "Отправить", FontSize = 14, Align = TextAnchor.MiddleCenter, Font = "RobotoCondensed-Bold.ttf", Color = "0.925 0.894 0.953 1" },
			}, StoreLayer + ".SupportPanel.InputPanel", StoreLayer + ".SupportPanel.SendButton");

			// Кнопка обновления (точно так же, как кнопка закрытия модального окна)
			// Используем тот же стиль: прозрачный фон, серый цвет текста (0.561 0.561 0.561 1)
			// Используем текстовый символ ↻ для обновления, как у кнопки закрытия используется ✕
			// Делаем кнопку строго квадратной с фиксированным размером в пикселях
			int refreshButtonSizePixels = 30; // Фиксированный размер кнопки в пикселях (30x30)
			float refreshButtonX = 0.9368f; // Центр кнопки по X (0.8946 + (0.979 - 0.8946) / 2)
			float refreshButtonY = 0.5f; // Центр кнопки по Y (центр по вертикали панели ввода)

			container.Add(new CuiButton
			{
				RectTransform = {
					AnchorMin = $"{refreshButtonX:F4} {refreshButtonY:F4}",
					AnchorMax = $"{refreshButtonX:F4} {refreshButtonY:F4}",
					OffsetMin = $"{-refreshButtonSizePixels / 2} {-refreshButtonSizePixels / 2}",
					OffsetMax = $"{refreshButtonSizePixels / 2} {refreshButtonSizePixels / 2}"
				},
				Button = { Color = "0 0 0 0", Command = "UI_GameStoresRUST support.refresh", Close = "" }, // Прозрачный фон как у кнопки закрытия
				Text = {
					Text = "↻", // Символ обновления
					Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-bold.ttf",
					FontSize = 20,
					Color = "0.561 0.561 0.561 1" // Серый цвет, как у крестика закрытия
				},
			}, StoreLayer + ".SupportPanel.InputPanel", StoreLayer + ".SupportPanel.RefreshButton");

			// Scroll панель для сообщений (будет обновлена при загрузке сообщений)
			// ContentTransform будет обновлен динамически в ShowSupportMessagesUI
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.Scroll",
				Parent = StoreLayer + ".SupportPanel.Content",
				Components =
				{
					new CuiImageComponent { Color = "1 1 1 0" },
					new CuiScrollViewComponent
					{
						MovementType = ScrollRect.MovementType.Elastic,
						Vertical = true,
						Horizontal = false,
						Inertia = true,
						Elasticity = 0.25f,
						DecelerationRate = 0.3f,
						ScrollSensitivity = 24f,
						ContentTransform = new CuiRectTransform { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0" },
					},
					new CuiRectTransformComponent { AnchorMin = "0 0.0966", AnchorMax = "1 0.993", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			CuiHelper.AddUi(player, container);

			// Обновляем навигационное меню - делаем активной кнопку "Поддержка"
			UpdateNavigationBar(player, "support");

			// Загружаем сообщения поддержки
			LoadSupportMessages(player);
		}

		// Загружает сообщения поддержки для игрока
		private void LoadSupportMessages(BasePlayer player)
		{
			PrintWarning($"[Support] LoadSupportMessages called for player {player.UserIDString}");

			// Сначала получаем список тикетов
			RequestSupport("support-game-stores/tickets", new Dictionary<string, string>(), (code, response) =>
			{
				PrintWarning($"[Support] LoadSupportMessages response: code={code}, response length={response?.Length ?? 0}");

				if (code == 200)
				{
					try
					{
						PrintWarning($"[Support] Parsing tickets response...");
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
						PrintWarning($"[Support] JSON parsed, keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");

						if (json != null && json.TryGetValue("data", out var dataObj))
						{
							PrintWarning($"[Support] Found 'data' key");
							var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(dataObj.ToString());
							PrintWarning($"[Support] Data parsed, keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");

							if (data != null && data.TryGetValue("tickets", out var ticketsObj))
							{
								PrintWarning($"[Support] Found 'tickets' key");
								var tickets = JsonConvert.DeserializeObject<List<Dictionary<string, object>>>(ticketsObj.ToString());
								PrintWarning($"[Support] Tickets count: {tickets?.Count ?? 0}");

								if (tickets != null && tickets.Count > 0)
								{
									// Ищем только открытый тикет (не показываем закрытые, если нет открытых)
									var openTicket = tickets.FirstOrDefault(t =>
										t.TryGetValue("status", out var status) && status?.ToString() == "open");

									if (openTicket != null)
									{
										PrintWarning($"[Support] Found open ticket: {(openTicket.TryGetValue("id", out var tid) ? tid.ToString() : "no id")}");

										if (openTicket.TryGetValue("id", out var ticketId))
										{
											string ticketNumber = ticketId.ToString();
											_playerSupportTickets[player.userID] = ticketNumber;
											PrintWarning($"[Support] Loading messages for open ticket {ticketNumber}");

											// Загружаем сообщения тикета
											LoadSupportTicketMessages(player, ticketNumber);
										}
										else
										{
											PrintWarning($"[Support] Open ticket has no ID, clearing messages");
											_playerSupportTickets.Remove(player.userID);
											_playerSupportMessages[player.userID] = new List<Dictionary<string, object>>();
											ShowSupportMessagesUI(player);
										}
									}
									else
									{
										PrintWarning($"[Support] No open tickets found, only closed tickets available. Showing default messages.");
										// Нет открытых тикетов - показываем автоматические системные сообщения
										_playerSupportTickets.Remove(player.userID);
										ShowDefaultSupportMessages(player);
									}
								}
								else
								{
									PrintWarning($"[Support] No tickets found, showing default messages");
									// Нет тикетов - показываем автоматические системные сообщения
									_playerSupportTickets.Remove(player.userID);
									ShowDefaultSupportMessages(player);
								}
							}
							else
							{
								PrintError($"[Support] No 'tickets' key in data. Data keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");
							}
						}
						else
						{
							PrintError($"[Support] No 'data' key in response. JSON keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");
							PrintError($"[Support] Full response: {response}");
						}
					}
					catch (Exception ex)
					{
						PrintError($"[Support] Error loading support tickets: {ex.Message}");
						PrintError($"[Support] Stack trace: {ex.StackTrace}");
						PrintError($"[Support] Response that caused error: {response}");
						ShowNotify(player, "Ошибка загрузки сообщений поддержки", true);
					}
				}
				else
				{
					PrintError($"[Support] LoadSupportMessages failed with code {code}");
					PrintError($"[Support] Response: {response}");
					ShowNotify(player, "Ошибка загрузки сообщений поддержки", true);
				}
			}, player);
		}

		// Загружает сообщения конкретного тикета
		private void LoadSupportTicketMessages(BasePlayer player, string ticketId)
		{
			PrintWarning($"[Support] LoadSupportTicketMessages called for ticket {ticketId}, player {player.UserIDString}");

			RequestSupport($"support-game-stores/tickets/{ticketId}", new Dictionary<string, string>(), (code, response) =>
			{
				PrintWarning($"[Support] LoadSupportTicketMessages response: code={code}, response length={response?.Length ?? 0}");

				if (code == 200)
				{
					try
					{
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
						PrintWarning($"[Support] Ticket messages JSON parsed, keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");

						if (json != null && json.TryGetValue("data", out var dataObj))
						{
							var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(dataObj.ToString());
							PrintWarning($"[Support] Ticket messages data parsed, keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");

							if (data != null && data.TryGetValue("messages", out var messagesObj))
							{
								var messages = JsonConvert.DeserializeObject<List<Dictionary<string, object>>>(messagesObj.ToString());
								PrintWarning($"[Support] Messages loaded: {messages?.Count ?? 0} messages");
								// Переворачиваем список сообщений, чтобы последние были первыми
								if (messages != null && messages.Count > 0)
								{
									messages.Reverse();
								}
								_playerSupportMessages[player.userID] = messages ?? new List<Dictionary<string, object>>();
								ShowSupportMessagesUI(player);
							}
							else
							{
								PrintError($"[Support] No 'messages' key in data. Data keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");
							}
						}
						else
						{
							PrintError($"[Support] No 'data' key in ticket messages response. JSON keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");
							PrintError($"[Support] Full response: {response}");
						}
					}
					catch (Exception ex)
					{
						PrintError($"[Support] Error loading support messages: {ex.Message}");
						PrintError($"[Support] Stack trace: {ex.StackTrace}");
						PrintError($"[Support] Response that caused error: {response}");
						ShowNotify(player, "Ошибка загрузки сообщений", true);
					}
				}
				else
				{
					PrintError($"[Support] LoadSupportTicketMessages failed with code {code}");
					PrintError($"[Support] Response: {response}");
					ShowNotify(player, "Ошибка загрузки сообщений", true);
				}
			}, player);
		}

		// Показывает автоматические системные сообщения когда нет активного тикета
		private void ShowDefaultSupportMessages(BasePlayer player)
		{
			PrintWarning($"[Support] ShowDefaultSupportMessages called for player {player.UserIDString}");

			// Создаем автоматическое системное сообщение (как на сайте)
			var defaultMessages = new List<Dictionary<string, object>>
			{
				new Dictionary<string, object>
				{
					{ "message", "Если вы хотите пожаловаться на игрока, нажмите в игре кнопку F7. Мы видим все ваши жалобы в игре, тикет в поддержку создавать не нужно. Если у вас есть доказательства и откаты вы можете приложить их по кнопке вложения файлов." },
					{ "created_at", DateTime.Now.ToString("dd.MM.yyyy HH:mm") },
					{ "user_id", null }, // Системное сообщение
					{ "user", null }
				}
			};

			_playerSupportMessages[player.userID] = defaultMessages;
			ShowSupportMessagesUI(player);
		}

		// Отображает сообщения поддержки в scroll панели
		private void ShowSupportMessagesUI(BasePlayer player)
		{
			PrintWarning($"[Support] ShowSupportMessagesUI called for player {player.UserIDString}");

			// Удаляем старые сообщения и контент
			for (int i = 0; i < 100; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".SupportPanel.Message{i}");
			}
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.Scroll.Content");
			// Также удаляем и пересоздаем скролл панель, чтобы обновить ContentTransform
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.Scroll");

			if (!_playerSupportMessages.TryGetValue(player.userID, out var messages) || messages == null || messages.Count == 0)
			{
				PrintWarning($"[Support] No messages to display for player {player.UserIDString}");
				return;
			}

			PrintWarning($"[Support] Displaying {messages.Count} messages for player {player.UserIDString}");

			var container = new CuiElementContainer();
			float spacing = 0.02f; // Отступ между сообщениями (увеличен для визуального разделения)
			// Расчет высоты строки на основе FontSize (как в HelpPanel: FontSize 14 -> itemHeight 0.04f)
			// Соотношение: 0.04 / 14 ≈ 0.00286 на единицу FontSize
			// Для FontSize 11: 11 * 0.00286 ≈ 0.0315, но используем немного меньше для более компактного вида
			float baseLineHeight = 0.031f; // Высота одной строки текста (FontSize 11, как в HelpPanel для FontSize 14)
			float paddingTop = 0.0005f; // Верхний отступ (уменьшен для более компактного вида)
			// Для даты FontSize 9: 9 * 0.00286 ≈ 0.0257
			float dateLineHeight = 0.026f; // Высота строки с датой (FontSize 9)

			// Более точный расчет: учитываем реальную ширину контейнера
			// Ширина контейнера сообщений: от 0.05 до 0.9942 = ~0.9442 (94.42% ширины экрана)
			// С учетом аватара (32px) и отступа слева (0.05), реальная ширина текста примерно 85-90% от ширины контейнера
			// При размере шрифта 11 и ширине примерно 1920px * 0.85 = ~1632px
			// Но с учетом того, что текст переносится по словам, а не по символам, реально примерно 50-55 символов на строку
			// Для более точного расчета используем меньшее значение
			// Для системных сообщений (без аватара) ширина больше, поэтому больше символов на строку
			int charsPerLine = 55; // Уменьшено для более точного расчета (учитывая переносы по словам)

			// Сначала вычисляем высоту каждого сообщения на основе размера текста
			var messageHeights = new List<float>();
			var messageLineCounts = new List<int>(); // Количество строк в каждом сообщении
			float totalHeight = 0f;

			for (int i = 0; i < messages.Count; i++)
			{
				var message = messages[i];
				string messageText = message.TryGetValue("message", out var msg) ? msg?.ToString() ?? "" : "";
				// Определяем, является ли сообщение системным (автоматическим)
				bool isSystem = !message.TryGetValue("user_id", out var userId) || userId == null;

				// Для системных сообщений (без аватара) ширина текста больше, поэтому больше символов на строку
				int currentCharsPerLine = isSystem ? 65 : charsPerLine; // Для системных сообщений больше символов на строку

				// Подсчитываем количество строк в тексте сообщения (более точный расчет)
				int textLineCount = 1; // Минимум одна строка для текста
				if (!string.IsNullOrEmpty(messageText))
				{
					// Учитываем переносы строк (явные \n)
					int newlineCount = messageText.Split('\n').Length;

					// Более точный расчет: учитываем реальную длину текста
					// Убираем HTML теги для более точного подсчета
					string textWithoutTags = System.Text.RegularExpressions.Regex.Replace(messageText, "<[^>]+>", "");

					// Убираем пробелы в начале и конце для более точного подсчета
					textWithoutTags = textWithoutTags.Trim();

					// Учитываем длину текста (для системных сообщений больше символов на строку)
					// Для более точного расчета учитываем, что длинные слова могут переноситься
					int textLines = (int)Math.Ceiling((double)textWithoutTags.Length / currentCharsPerLine);

					// Берем максимум из явных переносов строк и расчетных строк
					textLineCount = Math.Max(newlineCount, textLines);

					// Минимум 1 строка, даже если текст пустой (для даты)
					if (textLineCount < 1) textLineCount = 1;
				}
				messageLineCounts.Add(textLineCount);

				// Высота текста = высота строк текста + высота строки с датой
				// Используем более точную высоту строки
				float textHeight = (textLineCount * baseLineHeight) + dateLineHeight;

				// Высота сообщения = верхний отступ + высота текста + нижний отступ для даты
				// Добавляем небольшой дополнительный отступ снизу для даты
				float paddingBottom = 0.005f; // Отступ снизу для даты
				float messageHeight = paddingTop + textHeight + paddingBottom;
				messageHeights.Add(messageHeight);
				// Добавляем высоту сообщения и spacing только если это не последнее сообщение
				// spacing добавляется между сообщениями, а не после последнего
				if (i < messages.Count - 1)
				{
					totalHeight += messageHeight + spacing;
				}
				else
				{
					totalHeight += messageHeight;
				}
			}

			if (totalHeight < 1f) totalHeight = 1f;

			// Пересоздаем скролл панель с правильным ContentTransform на основе totalHeight
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.Scroll",
				Parent = StoreLayer + ".SupportPanel.Content",
				Components =
				{
					new CuiImageComponent { Color = "1 1 1 0" },
					new CuiScrollViewComponent
					{
						MovementType = ScrollRect.MovementType.Elastic,
						Vertical = true,
						Horizontal = false,
						Inertia = true,
						Elasticity = 0.25f,
						DecelerationRate = 0.3f,
						ScrollSensitivity = 24f,
						// ContentTransform с отрицательным OffsetMin для размещения контента снизу вверх
						// Контент будет начинаться снизу (y=0) и растягиваться вверх на totalHeight
						ContentTransform = new CuiRectTransform { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = $"0 -{totalHeight:F4}", OffsetMax = "0 0" },
					},
					new CuiRectTransformComponent { AnchorMin = "0 0.0966", AnchorMax = "1 0.993", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			// Создаем контейнер для контента scroll панели
			// Контент должен быть размещен снизу вверх, чтобы последние сообщения были внизу и видны сразу
			// Используем AnchorMin = "0 0", AnchorMax = "1 1" - контент заполняет весь доступный размер
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.Scroll.Content",
				Parent = StoreLayer + ".SupportPanel.Scroll",
				Components =
				{
					new CuiImageComponent { Color = "1 1 1 0" },
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 1",
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					},
				}
			});

			// Размещаем сообщения снизу вверх, начиная с самого низа (0)
			// Последние сообщения будут внизу и видны сразу
			float currentY = 0f; // Начинаем снизу контента

			// Проходим по сообщениям в прямом порядке (от первого к последнему)
			// чтобы последние сообщения были внизу
			for (int i = 0; i < messages.Count; i++)
			{
				var message = messages[i];
				string messageText = message.TryGetValue("message", out var msg) ? msg?.ToString() ?? "" : "";
				string createdAt = message.TryGetValue("created_at", out var created) ? created?.ToString() ?? "" : "";
				// Если дата пустая, используем текущую дату
				if (string.IsNullOrEmpty(createdAt))
				{
					createdAt = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
				}
				bool isSystem = !message.TryGetValue("user_id", out var userId) || userId == null;
				string avatarUrl = "https://images.seeklogo.com/logo-png/39/2/rust-logo-png_seeklogo-399370.png";

				if (!isSystem && message.TryGetValue("user", out var userObj) && userObj != null)
				{
					Dictionary<string, object> user = null;

					// Проверяем тип объекта - может быть уже Dictionary, JObject или JSON строка
					if (userObj is Dictionary<string, object>)
					{
						user = userObj as Dictionary<string, object>;
					}
					else
					{
						// Пытаемся десериализовать из JSON строки или другого типа
						try
						{
							// Если это уже объект, сериализуем и десериализуем обратно
							string userJson = userObj is string ? userObj.ToString() : JsonConvert.SerializeObject(userObj);
							user = JsonConvert.DeserializeObject<Dictionary<string, object>>(userJson);
						}
						catch (Exception ex)
						{
							PrintError($"[Support] Error processing user object for message {i}: {ex.Message}, type: {userObj.GetType().Name}");
						}
					}

					if (user != null && user.TryGetValue("avatar", out var avatar) && avatar != null)
					{
						avatarUrl = avatar.ToString();
						PrintWarning($"[Support] Message {i}: avatar URL: {avatarUrl}");
					}
					else
					{
						PrintWarning($"[Support] Message {i}: avatar not found, user keys: {(user != null ? string.Join(", ", user.Keys) : "null")}");
					}
				}

				// Получаем высоту этого сообщения
				float messageHeight = messageHeights[i];

				// Панель сообщения
				string messageName = StoreLayer + $".SupportPanel.Message{i}";
				float messageTop = currentY + messageHeight; // Верх сообщения = текущая позиция + высота

				container.Add(new CuiElement
				{
					Name = messageName,
					Parent = StoreLayer + ".SupportPanel.Scroll.Content",
					Components =
					{
						new CuiImageComponent { Color = "1 1 1 0" },
						new CuiRectTransformComponent
						{
							AnchorMin = $"0 {currentY:F4}",
							AnchorMax = $"1 {messageTop:F4}",
							OffsetMin = "0 0",
							OffsetMax = "0 0"
						},
					}
				});

				// Получаем количество строк для этого сообщения (индекс i соответствует индексу в messageLineCounts)
				int textLineCount = messageLineCounts[i];

				// Вычисляем динамические отступы для текста на основе размера текста
				// Верхний отступ - минимальный (одинаковый для всех сообщений)
				float textTop = 1f - paddingTop; // Верхний отступ от верха сообщения
				// Нижний отступ - небольшой отступ снизу для даты, чтобы она была видна
				float paddingBottom = 0.005f; // Отступ снизу для даты
				float textBottom = paddingBottom; // Небольшой отступ снизу для даты

				// Аватар (только для несистемных сообщений) - строго квадратный, слева от текста
				if (!isSystem)
				{
					// Делаем аватар строго квадратным, используя фиксированный размер в пикселях
					// Позиционируем аватар слева от текста, выровненный по верхнему краю текста
					int avatarSizePixels = 26; // Фиксированный размер аватара в пикселях (26x26, уменьшен на 20% с 32)

					PrintWarning($"[Support] Creating avatar for message {i}, URL: {avatarUrl}, textTop: {textTop:F4}, isSystem: {isSystem}");

					// Позиционируем аватар: якорь в левом верхнем углу текста (0, textTop)
					// OffsetMin: (0, -avatarSizePixels) - отступ вниз на размер аватара от якоря
					// OffsetMax: (avatarSizePixels, 0) - отступ вправо на размер аватара от якоря
					// Это создаст квадрат 32x32 пикселей, верхний край которого совпадает с верхним краем текста
					container.Add(new CuiElement
					{
						Name = messageName + ".Avatar",
						Parent = messageName,
						Components =
						{
							new CuiRawImageComponent { Color = "1 1 1 1", Url = avatarUrl },
							new CuiRectTransformComponent
							{
								AnchorMin = "0 " + textTop.ToString("F4"),
								AnchorMax = "0 " + textTop.ToString("F4"),
								OffsetMin = $"0 -{avatarSizePixels}",
								OffsetMax = $"{avatarSizePixels} 0"
							},
						}
					});
				}
				else
				{
					PrintWarning($"[Support] Skipping avatar for message {i} (isSystem: {isSystem})");
				}

				// Текст сообщения (с отступом слева, если есть аватар)
				// Всегда добавляем дату под текстом сообщения
				string displayText = string.IsNullOrEmpty(messageText)
					? $"<size=9><color=#999>{createdAt}</color></size>"
					: $"{messageText}\n<size=9><color=#999>{createdAt}</color></size>";
				// Для системных сообщений без аватара - начинаем с левого края, для обычных - с отступом после квадратного аватара
				// Аватар 32 пикселя, отступ между аватаром и текстом
				string textAnchorMinX = isSystem ? "0" : "0.04"; // Отступ после квадратного аватара

				// Для автоматических сообщений делаем текст на 30% прозрачнее (альфа 0.7 вместо 1.0)
				// Отступы одинаковые для всех типов сообщений
				string textColor = isSystem ? "0.925 0.894 0.953 0.7" : "0.925 0.894 0.953 1";
				container.Add(new CuiLabel
				{
					// Динамические отступы: сверху небольшой отступ, снизу отступ зависит от размера текста
					// Одинаковые отступы для системных и пользовательских сообщений
					RectTransform = {
						AnchorMin = $"{textAnchorMinX} {textBottom:F4}",
						AnchorMax = "0.9942 " + textTop.ToString("F4"),
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					},
					Text = { Text = displayText, FontSize = 11, Align = TextAnchor.UpperLeft, Font = "RobotoCondensed-Bold.ttf", Color = textColor },
				}, messageName, messageName + ".Text");

				// Перемещаемся вверх для следующего сообщения
				// spacing добавляется только между сообщениями, а не после последнего
				if (i < messages.Count - 1)
				{
					currentY = messageTop + spacing;
				}
				else
				{
					currentY = messageTop;
				}
			}

			CuiHelper.AddUi(player, container);
		}


		// Отправляет сообщение в поддержку
		private void SendSupportMessage(BasePlayer player, string messageText)
		{
			PrintWarning($"[Support] SendSupportMessage called for player {player.UserIDString}, message length: {messageText?.Length ?? 0}");

			if (string.IsNullOrWhiteSpace(messageText))
			{
				PrintWarning($"[Support] Message is empty, aborting");
				ShowNotify(player, "Сообщение не может быть пустым", true);
				return;
			}

			// Проверяем, есть ли активный тикет
			if (!_playerSupportTickets.TryGetValue(player.userID, out var ticketId) || string.IsNullOrEmpty(ticketId))
			{
				PrintWarning($"[Support] No active ticket, creating new one");
				// Сначала оптимистично добавляем сообщение в UI (как будто тикет уже открыт)
				// Затем создаем тикет, который автоматически создаст системные сообщения
				AddOptimisticMessage(player, messageText);

				// Создаем новый тикет (API автоматически создаст системные сообщения и добавит пользовательское)
				CreateSupportTicket(player, messageText);
			}
			else
			{
				PrintWarning($"[Support] Sending message to existing ticket {ticketId}");
				// Отправляем сообщение в существующий тикет
				var args = new Dictionary<string, string>
				{
					{ "message", messageText }
				};

				RequestSupport($"support-game-stores/tickets/{ticketId}/messages", args, (code, response) =>
				{
					PrintWarning($"[Support] SendSupportMessage response: code={code}");

					if (code == 200 || code == 201)
					{
						PrintWarning($"[Support] Message sent successfully, reloading messages");
						// Очищаем поле ввода и обновляем сообщения
						ClearSupportInput(player);
						LoadSupportTicketMessages(player, ticketId);
						ShowNotify(player, "Сообщение отправлено", false);
					}
					else
					{
						PrintError($"[Support] Failed to send message, code={code}, response={response}");
						try
						{
							var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
							string errorMsg = "Ошибка отправки сообщения";
							if (json != null && json.TryGetValue("message", out var msg))
							{
								errorMsg = msg.ToString();
							}
							ShowNotify(player, errorMsg, true);
						}
						catch (Exception ex)
						{
							PrintError($"[Support] Error parsing error response: {ex.Message}");
							ShowNotify(player, "Ошибка отправки сообщения", true);
						}
					}
				}, player);
			}
		}

		// Оптимистично добавляет сообщение в UI до создания тикета
		private void AddOptimisticMessage(BasePlayer player, string messageText)
		{
			PrintWarning($"[Support] AddOptimisticMessage called for player {player.UserIDString}");

			// Получаем текущие сообщения или создаем новый список
			if (!_playerSupportMessages.TryGetValue(player.userID, out var messages))
			{
				messages = new List<Dictionary<string, object>>();
			}

			// Добавляем временное сообщение пользователя (будет заменено после загрузки с сервера)
			// Используем дефолтный аватар, так как GetUserAvatar может не существовать
			var tempMessage = new Dictionary<string, object>
			{
				{ "message", messageText },
				{ "created_at", DateTime.Now.ToString("dd.MM.yyyy HH:mm") },
				{ "user_id", player.userID },
				{ "user", new Dictionary<string, object>
					{
						{ "avatar", "https://images.seeklogo.com/logo-png/39/2/rust-logo-png_seeklogo-399370.png" }
					}}
			};

			messages.Add(tempMessage);
			_playerSupportMessages[player.userID] = messages;

			// Обновляем UI
			ShowSupportMessagesUI(player);
		}

		// Создает новый тикет поддержки
		private void CreateSupportTicket(BasePlayer player, string messageText)
		{
			PrintWarning($"[Support] CreateSupportTicket called for player {player.UserIDString}, message length: {messageText?.Length ?? 0}");

			var args = new Dictionary<string, string>
			{
				{ "message", messageText }
			};

			// Используем правильный путь для создания тикета
			RequestSupport("support-game-stores/create", args, (code, response) =>
			{
				PrintWarning($"[Support] CreateSupportTicket response: code={code}, response length={response?.Length ?? 0}");

				if (code == 200 || code == 201)
				{
					try
					{
						PrintWarning($"[Support] Parsing create ticket response...");
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
						PrintWarning($"[Support] Create ticket JSON parsed, keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");

						// Проверяем success флаг
						if (json != null && json.TryGetValue("success", out var successObj) && successObj?.ToString().ToLower() == "false")
						{
							PrintError($"[Support] API returned success=false");
							string errorMsg = "Ошибка создания тикета";
							if (json.TryGetValue("message", out var msg))
							{
								errorMsg = msg.ToString();
							}
							ShowNotify(player, errorMsg, true);
							return;
						}

						if (json != null && json.TryGetValue("data", out var dataObj))
						{
							var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(dataObj.ToString());
							PrintWarning($"[Support] Create ticket data parsed, keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");

							if (data != null && data.TryGetValue("ticket", out var ticketObj))
							{
								var ticket = JsonConvert.DeserializeObject<Dictionary<string, object>>(ticketObj.ToString());
								PrintWarning($"[Support] Create ticket ticket parsed, keys: {(ticket != null ? string.Join(", ", ticket.Keys) : "null")}");

								if (ticket != null && ticket.TryGetValue("id", out var ticketId))
								{
									string ticketNumber = ticketId.ToString();
									PrintWarning($"[Support] Ticket created successfully with ID: {ticketNumber}");
									_playerSupportTickets[player.userID] = ticketNumber;

									// Очищаем поле ввода
									ClearSupportInput(player);

									// Загружаем все сообщения тикета (включая автоматические системные сообщения)
									// Это заменит оптимистично добавленное сообщение на полные данные с сервера
									LoadSupportTicketMessages(player, ticketNumber);
									ShowNotify(player, "Сообщение отправлено", false);
								}
								else
								{
									PrintError($"[Support] Ticket has no ID. Ticket keys: {(ticket != null ? string.Join(", ", ticket.Keys) : "null")}");
									ShowNotify(player, "Ошибка создания тикета: нет ID тикета", true);
								}
							}
							else
							{
								PrintError($"[Support] No 'ticket' key in data. Data keys: {(data != null ? string.Join(", ", data.Keys) : "null")}");
								ShowNotify(player, "Ошибка создания тикета: неверный формат ответа", true);
							}
						}
						else
						{
							PrintError($"[Support] No 'data' key in create ticket response. JSON keys: {(json != null ? string.Join(", ", json.Keys) : "null")}");
							PrintError($"[Support] Full response: {response}");
							ShowNotify(player, "Ошибка создания тикета: неверный формат ответа", true);
						}
					}
					catch (Exception ex)
					{
						PrintError($"[Support] Error creating support ticket: {ex.Message}");
						PrintError($"[Support] Stack trace: {ex.StackTrace}");
						PrintError($"[Support] Response that caused error: {response}");
						ShowNotify(player, "Ошибка создания тикета", true);
					}
				}
				else
				{
					PrintError($"[Support] CreateSupportTicket failed with code {code}");
					PrintError($"[Support] Response: {response}");
					try
					{
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
						string errorMsg = "Ошибка создания тикета";
						if (json != null && json.TryGetValue("message", out var msg))
						{
							errorMsg = msg.ToString();
						}
						ShowNotify(player, errorMsg, true);
					}
					catch (Exception ex)
					{
						PrintError($"[Support] Error parsing error response: {ex.Message}");
						ShowNotify(player, "Ошибка создания тикета", true);
					}
				}
			}, player);
		}

		// Очищает поле ввода поддержки
		private void ClearSupportInput(BasePlayer player)
		{
			PrintWarning($"[Support] ClearSupportInput called for player {player.UserIDString}");

			// Удаляем все элементы поля ввода (в том же порядке, как они создаются)
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.InputFieldBg");
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.InputLabel");
			CuiHelper.DestroyUi(player, StoreLayer + ".SupportPanel.InputField");

			// Очищаем сохраненный текст и фокус
			_playerSupportInputText.Remove(player.userID);
			_playerSupportInputFocused.Remove(player.userID);
			_playerSupportLastInputText.Remove(player.userID);

			// Восстанавливаем поле ввода точно так же, как в ShowSupportUI (в том же порядке)
			var container = new CuiElementContainer();

			// 1. Поле ввода сообщения с фоном (как в ShowSupportUI)
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputFieldBg",
				Parent = StoreLayer + ".SupportPanel.InputPanel",
				Components =
				{
					new CuiImageComponent { Color = "0.098 0.063 0.176 1" }, // #19102d - исходный цвет
					new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0.592 1", OffsetMin = "0 0", OffsetMax = "0 0" },
				}
			});

			// 2. Label "Введите сообщение" (как в ShowSupportUI)
			container.Add(new CuiButton
			{
				RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.58 1", OffsetMin = "0 0", OffsetMax = "0 0" },
				Button = { Color = "0 0 0 0", Command = "UI_GameStoresRUST support.inputfocus", Close = "" },
				Text = { Text = "Введите сообщение", FontSize = 12, Align = TextAnchor.MiddleLeft, Font = "RobotoCondensed-Bold.ttf", Color = "0.6 0.6 0.6 1" },
			}, StoreLayer + ".SupportPanel.InputPanel", StoreLayer + ".SupportPanel.InputLabel");

			// 3. Панель для поля ввода (как в ShowSupportUI)
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputField",
				Parent = StoreLayer + ".SupportPanel.InputPanel",
				Components =
				{
					new CuiImageComponent { Color = "0 0 0 0" },
					new CuiRectTransformComponent { AnchorMin = "-0.0044 0", AnchorMax = "0.592 1", OffsetMin = "15 0", OffsetMax = "-15 0" },
				}
			});

			// 4. Вложенный элемент с InputFieldComponent (как в ShowSupportUI)
			container.Add(new CuiElement
			{
				Name = StoreLayer + ".SupportPanel.InputField.Current",
				Parent = StoreLayer + ".SupportPanel.InputField",
				Components =
				{
					new CuiInputFieldComponent
					{
						Text = "",
						Font = "RobotoCondensed-Bold.ttf",
						FontSize = 14,
						Align = TextAnchor.MiddleLeft,
						Color = "0.925 0.894 0.953 1",
						Command = "UI_GameStoresRUST support.input",
						CharsLimit = 500,
						NeedsKeyboard = true,
					},
					new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1" },
				}
			});

			CuiHelper.AddUi(player, container);

			PrintWarning($"[Support] Input field cleared and reset for player {player.UserIDString}");
		}

		// Загружает данные для раздела помощи один раз при инициализации плагина
		private void LoadHelpInfo()
		{
			Request("server.helpInfo", new Dictionary<string, string>(), (code, response) =>
			{
				if (code == 200)
				{
					try
					{
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response);
						if (json != null && json.TryGetValue("data", out var dataObj))
						{
							var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(dataObj.ToString());
							if (data != null)
							{
								// Сохраняем в кеш
								_helpInfoCache = data;
							}
						}
					}
					catch (Exception ex)
					{
						PrintError($"Error loading help info: {ex.Message}");
					}
				}
			});
		}

		// Применяет кешированные данные helpInfo к UI игрока
		private void ApplyHelpInfoToUI(BasePlayer player)
		{
			if (_helpInfoCache == null) return;

			// Обновляем информацию о вайпах
			if (_helpInfoCache.TryGetValue("wipeInfo", out var wipeInfoObj))
			{
				var wipeInfo = JsonConvert.DeserializeObject<Dictionary<string, object>>(wipeInfoObj.ToString());
				if (wipeInfo != null)
				{
					var lastWipe = wipeInfo.TryGetValue("lastWipe", out var lw) ? lw?.ToString() : null;
					var nextWipe = wipeInfo.TryGetValue("nextWipe", out var nw) ? nw?.ToString() : null;
					var nextGlobalWipe = wipeInfo.TryGetValue("nextGlobalWipe", out var ngw) ? ngw?.ToString() : null;
					UpdateHelpWipeInfo(player, lastWipe, nextWipe, nextGlobalWipe);
				}
			}

			// Обновляем команды
			if (_helpInfoCache.TryGetValue("commands", out var commandsObj))
			{
				// commands может быть уже массивом или объектом с вложенной структурой
				List<object> commands = null;

				if (commandsObj is List<object> directList)
				{
					commands = directList;
				}
				else if (commandsObj is Dictionary<string, object> commandsDict)
				{
					// Если это объект с вложенной структурой (старый формат)
					if (commandsDict.TryGetValue("data", out var nestedData))
					{
						commands = JsonConvert.DeserializeObject<List<object>>(nestedData.ToString());
					}
				}
				else
				{
					// Пробуем десериализовать как массив
					try
					{
						commands = JsonConvert.DeserializeObject<List<object>>(commandsObj.ToString());
					}
					catch
					{
						// Если не получилось, пробуем как объект с data
						var commandsDictFallback = JsonConvert.DeserializeObject<Dictionary<string, object>>(commandsObj.ToString());
						if (commandsDictFallback != null && commandsDictFallback.TryGetValue("data", out var nestedData))
						{
							commands = JsonConvert.DeserializeObject<List<object>>(nestedData.ToString());
						}
					}
				}

				if (commands != null)
				{
					UpdateHelpCommands(player, commands);
				}
			}
		}

		private void UpdateHelpWipeInfo(BasePlayer player, string lastWipe, string nextWipe, string nextGlobalWipe)
		{
			// Удаляем старые текстовые элементы перед обновлением
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.LastWipeBox.Text");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Text");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.Content");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.NextWipeBox.GlobalBadge");

			var container = new CuiElementContainer();

			// Обновляем информацию о последнем вайпе
			string lastWipeText = lastWipe ?? "НЕИЗВЕСТНО";

			// Плашка уже создана в ShowHelpUI, просто обновляем текст внутри
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Text = {Text = lastWipeText, Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel.LastWipeBox", StoreLayer + ".HelpPanel.LastWipeBox.Text");

			// Обновляем информацию о следующем вайпе
			string nextWipeText = nextWipe ?? "НЕИЗВЕСТНО";
			bool isGlobal = !string.IsNullOrEmpty(nextWipe) && !string.IsNullOrEmpty(nextGlobalWipe) && nextWipe == nextGlobalWipe;

			// Создаем контейнер для содержимого (прозрачный, без фона)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон
			}, StoreLayer + ".HelpPanel.NextWipeBox", StoreLayer + ".HelpPanel.NextWipeBox.Content");

			// Текст следующего вайпа
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = isGlobal ? "0.7 1" : "1 1", OffsetMax = "0 0"},
				Text = {Text = nextWipeText, Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 16, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel.NextWipeBox.Content");

			// Красный бэйдж "ГЛОБАЛ" если следующий вайп = глобальному
			if (isGlobal)
			{
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = "0.75 0.2", AnchorMax = "0.95 0.8", OffsetMax = "0 0"},
					Image = {Color = "0.9 0.3 0.1 1"} // Красный фон
				}, StoreLayer + ".HelpPanel.NextWipeBox.Content", StoreLayer + ".HelpPanel.NextWipeBox.GlobalBadge");
				container.Add(new CuiLabel
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
					Text = {Text = "ГЛОБАЛ", Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf", FontSize = 10, Color = "1 1 1 1"}
				}, StoreLayer + ".HelpPanel.NextWipeBox.GlobalBadge");
			}

			CuiHelper.AddUi(player, container);
		}

		private void UpdateHelpCommands(BasePlayer player, List<object> commandsData)
		{
			// Удаляем старый заголовок, заглушку и команды
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsTitle");
			CuiHelper.DestroyUi(player, StoreLayer + ".HelpPanel.CommandsList");
			for (int i = 0; i < 50; i++) // Удаляем до 50 старых команд
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".HelpPanel.Command{i}");
			}

			var container = new CuiElementContainer();

			var commands = new List<string>();

			foreach (var item in commandsData)
			{

				Dictionary<string, object> category = null;
				string catName = "";

				// Пробуем разные способы получения категории
				if (item is Dictionary<string, object> dict)
				{
					category = dict;
				}
				else if (item is Newtonsoft.Json.Linq.JObject jObj)
				{
					// Конвертируем JObject в Dictionary
					category = jObj.ToObject<Dictionary<string, object>>();
				}
				else
				{
					// Пробуем десериализовать
					try
					{
						category = JsonConvert.DeserializeObject<Dictionary<string, object>>(item?.ToString() ?? "{}");
					}
					catch (Exception ex)
		{
						continue;
					}
				}

				if (category == null)
				{
					continue;
				}

				if (category.TryGetValue("category", out var categoryName))
				{
					catName = categoryName?.ToString() ?? "";

					// Получаем команды из категории "Команды сервера"
					bool hasCommands = category.TryGetValue("commands", out var commandsObj);

					if (catName == "Команды сервера" && hasCommands)
					{

						// commandsObj может быть List<object>, JArray или нужно десериализовать
						List<object> commandsList = null;

						if (commandsObj is List<object> directList)
						{
							commandsList = directList;
						}
						else if (commandsObj is Newtonsoft.Json.Linq.JArray jArray)
						{
							// Если это JArray, конвертируем в List<object>
							commandsList = jArray.ToObject<List<object>>();
						}
						else if (commandsObj is Newtonsoft.Json.Linq.JToken jToken)
						{
							// Если это JToken, пробуем конвертировать в массив
							if (jToken.Type == Newtonsoft.Json.Linq.JTokenType.Array)
							{
								commandsList = jToken.ToObject<List<object>>();
							}
						}
						else
						{
							// Пробуем десериализовать
							try
							{
								commandsList = JsonConvert.DeserializeObject<List<object>>(commandsObj.ToString());
							}
							catch (Exception ex)
							{
							}
						}

						if (commandsList != null)
						{
							foreach (var cmd in commandsList)
							{
								if (cmd is Dictionary<string, object> cmdDict)
								{
									if (cmdDict.TryGetValue("command", out var cmdText))
									{
										commands.Add(cmdText?.ToString() ?? "");
									}
								}
								else if (cmd is Newtonsoft.Json.Linq.JObject jObj)
								{
									// Если это JObject, пробуем получить command
									var cmdText = jObj["command"]?.ToString();
									if (!string.IsNullOrEmpty(cmdText))
									{
										commands.Add(cmdText);
									}
								}
								else
								{
									commands.Add(cmd?.ToString() ?? "");
								}
							}
						}
					}

					// Админку добавляем отдельно
					if (catName == "Админка" && category.TryGetValue("url", out var url))
					{
						commands.Add($"Админка: {url}");
					}
				}
			}

			// Если команд нет, не показываем ничего
			if (commands.Count == 0)
			{
				return; // Не показываем заголовок и команды, если их нет
			}

			// Заголовок "Доступные команды на сервере" (по левому краю, с таким же отступом как блок "ОНЛАЙН")
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.05 0.72", AnchorMax = "0.9 0.77", OffsetMax = "0 0"},
				Text = {Text = "Доступные команды на сервере", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 18, Color = "1 1 1 1"}
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.CommandsTitle");

			float startY = 0.70f; // Ближе к заголовку (было 0.67)
			float itemHeight = 0.04f;
			float spacing = 0.0096f; // Уменьшено еще на 20% (было 0.012, теперь 0.0096)

			for (int i = 0; i < commands.Count; i++)
			{
				float yPos = startY - (i * (itemHeight + spacing));
				float yMin = yPos - itemHeight;
				float yMax = yPos;

				string commandText = commands[i];

				// Разделяем команду и описание
				string[] parts = null;

				if (commandText.Contains(":"))
				{
					parts = commandText.Split(new[] { ':' }, 2);
				}
				else if (commandText.Contains(" - "))
				{
					parts = commandText.Split(new[] { " - " }, 2, StringSplitOptions.None);
				}

				if (parts != null && parts.Length == 2)
				{
					string cmd = parts[0].Trim();
					string desc = parts[1].Trim();

					// Команда (жирный, красный текст) - выровнена по левому краю с таким же отступом как блок "ОНЛАЙН"
					container.Add(new CuiLabel
			{
						RectTransform = {AnchorMin = $"0.05 {yMin:F2}", AnchorMax = $"0.9 {yMax:F2}", OffsetMax = "0 0"},
						Text = {Text = cmd, Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "0.9 0.3 0.1 1"} // Красный, жирный
					}, StoreLayer + ".HelpPanel", StoreLayer + $".HelpPanel.Command{i}.Cmd");

					// Дефис и описание - накладываются поверх команды, начиная сразу после текста
					// Используем тот же контейнер, но с отступом слева, равным ширине команды в пикселях
					// Примерно 7-8 пикселей на символ для шрифта 14px
					int cmdPixelWidth = cmd.Length * 7;
					container.Add(new CuiLabel
					{
						RectTransform = {AnchorMin = $"0.05 {yMin:F2}", AnchorMax = $"0.9 {yMax:F2}", OffsetMin = $"{cmdPixelWidth} 0", OffsetMax = "0 0"},
						Text = {Text = $" - {desc}", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "1 1 1 1"}
					}, StoreLayer + ".HelpPanel", StoreLayer + $".HelpPanel.Command{i}.Desc");
				}
				else
				{
					// Если нет разделителя, показываем только команду (жирный, красный) с таким же отступом как блок "ОНЛАЙН"
					container.Add(new CuiLabel
			{
						RectTransform = {AnchorMin = $"0.05 {yMin:F2}", AnchorMax = $"0.9 {yMax:F2}", OffsetMax = "0 0"},
						Text = {Text = commandText, Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "0.9 0.3 0.1 1"} // Красный, жирный
					}, StoreLayer + ".HelpPanel", StoreLayer + $".HelpPanel.Command{i}");
				}
			}

			// Текст с инструкцией после списка команд (с таким же отступом как блок "ОНЛАЙН")
			float instructionY = startY - (commands.Count * (itemHeight + spacing)) - spacing * 2; // Отступ после последней команды
			float instructionHeight = 0.03f;

			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = $"0.05 {instructionY - instructionHeight:F2}", AnchorMax = $"0.9 {instructionY:F2}", OffsetMax = "0 0"},
				Text = {Text = "Чтобы приобрести товары и вывести их на сервер в игре, зайдите на сайт prostoj.store", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-regular.ttf", FontSize = 12, Color = "0.7 0.7 0.7 1"}
			}, StoreLayer + ".HelpPanel", StoreLayer + ".HelpPanel.Instruction");

			// Нижняя граница меню (border-bottom) - 2px под меню (добавляем последним, чтобы было поверх всех элементов)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0.92", AnchorMax = "1 0.92", OffsetMin = "0 -2", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон, так как modal_background уже используется
			}, StoreLayer + ".Window", StoreLayer + ".TopNavBorder");

			CuiHelper.AddUi(player, container);
		}

		#region UI.Components

		private int basketItemsPerPage => _config.UI.ItemOnString * _config.UI.StringAmount;

		private void ShowBasketItemsUI(BasePlayer player,
			int page)
		{
			if (!_playerBaskets.TryGetValue(player.userID, out var playerBasket))
				return;

			// Удаляем старые карточки товаров перед отображением новых
			for (int i = 0; i < basketItemsPerPage; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{i}");
			}

			var wItems = playerBasket.GetItems(page, basketItemsPerPage);

			var secondContainer = new CuiElementContainer();

			// Пагинация справа от меню (в навигационной панели)
			UpdatePaginationInNavBar(player, page, playerBasket.GetCount());

			// Создаем все карточки товаров - каждая карточка создается полностью в ShowBasketItemUI
			// Это обеспечивает правильный z-order, как у карточек моментальной покупки
			for (var index = 0; index < wItems.Count; index++)
			{
				var item = wItems[index];

				// Вычисляем позицию для каждой карточки
				UI_RecountPosition(out var xSwitch, out var ySwitch, index, basketItemsPerPage);

				ShowBasketItemUI(player, item, ref secondContainer, index, (float)xSwitch, (float)ySwitch);
			}

			// Добавляем карточки товаров последними, чтобы они были на переднем плане
			// В Rust UI порядок элементов в контейнере определяет z-order - элементы, добавленные позже, отображаются поверх
			CuiHelper.AddUi(player, secondContainer);
			CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Text");
		}

		private void ShowBasketItemUI(BasePlayer player, WItem item, ref CuiElementContainer secondContainer,
			int index, float xPos, float yPos)
		{
			// Контейнер для карточки - создаем его здесь, как в ShowPopularItemUI, чтобы обеспечить правильный z-order
			secondContainer.Add(new CuiElement
			{
				Parent = StoreLayer + ".BlockPanel",
				Name = StoreLayer + $".BlockPanel.{index}",
				Components =
				{
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0.55", // Привязываем к левому краю панели
						AnchorMax = "0 0.55", // Привязываем к левому краю панели
						OffsetMin = $"{xPos} {yPos - _config.UI.ItemSide}",
						OffsetMax = $"{xPos + _config.UI.ItemSide} {yPos}"
					}
				}
			});

			// Определяем, какое изображение фона использовать в зависимости от статуса
			string cardImageName = ImageCard; // По умолчанию
			string cardImageUrl = "https://storage.prostoj.store/plugin-file/card.png";

			// Если товар в ожидании (обрабатывается), используем серую карточку
			if (!CanRequestGetItem(Convert.ToInt32(item.basketId)))
			{
				cardImageName = ImageCardGray;
				cardImageUrl = "https://storage.prostoj.store/plugin-file/card_gray.png";
			}
			// Если товар заблокирован, используем красную карточку
			else if (item.IsBlocked)
			{
				cardImageName = ImageCardRed;
				cardImageUrl = "https://storage.prostoj.store/plugin-file/card_red.png";
			}

			// Используем кешированное изображение, если доступно
			string cachedCardId = GetCachedImageId(cardImageName);
			if (!string.IsNullOrEmpty(cachedCardId))
			{
				cardImageUrl = null; // Указываем, что используем кеш
			}

			// Получаем кешированное изображение light.png
			string cachedLightId = GetCachedImageId(ImageLight);

			// Если кеш недоступен, пробуем получить напрямую из ImageCache
			if (string.IsNullOrEmpty(cachedLightId) && _imageCache != null)
			{
				if (_imageCache.IsImageLoaded("light"))
				{
					cachedLightId = _imageCache.GetImageId("light");
				}
			}

			// Изображение light.png на всю карточку как прозрачный фон (добавляем первым, под card изображениями)
			secondContainer.Add(new CuiElement
			{
				Parent = StoreLayer + $".BlockPanel.{index}",
				Name = StoreLayer + $".BlockPanel.{index}.LightBg",
				Components =
				{
					!string.IsNullOrEmpty(cachedLightId)
						? new CuiRawImageComponent {Png = cachedLightId, Color = "1 1 1 1"}
						: new CuiRawImageComponent {Url = "https://storage.prostoj.store/plugin-file/light.png", Color = "1 1 1 1"},
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 1",
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					}
				}
			});

			// Изображение card.png/card_red.png/card_gray.png на всю карточку как основной фон (добавляем вторым, поверх light.png)
			secondContainer.Add(new CuiElement
			{
				Parent = StoreLayer + $".BlockPanel.{index}",
				Name = StoreLayer + $".BlockPanel.{index}.CardBg",
				Components =
				{
					!string.IsNullOrEmpty(cachedCardId)
						? new CuiRawImageComponent {Png = cachedCardId}
						: GetCardImageComponent(cardImageName, cardImageUrl ?? "https://storage.prostoj.store/plugin-file/card.png"),
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 1",
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					}
				}
			});

			if (item.IsBlueprint) ItemBlueprintImageUI(ref secondContainer, index);

			// Изображение предмета внутри карточки (уменьшено на 20%)
			// Размер карточки ItemSide (например, 150px), 20% = 30px, значит нужно добавить по 15px с каждой стороны
			// Отступы увеличены с 3 до 18 пикселей для уменьшения изображения на 20%
			if (_config.Plugin.LoadSpriteImages && item.IsValid && item.itemDefinition != null)
			{
				secondContainer.Add(new CuiElement
				{
					Parent = StoreLayer + $".BlockPanel.{index}",
					Components =
					{
						new CuiImageComponent {ItemId = item.itemDefinition.itemid},
						new CuiRectTransformComponent
							{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
					}
				});
			}
			else
			{
				// Показываем изображение только если ImageUrl не пустой
				// НО только если у товара нет item_id (для товаров с item_id используется CuiImageComponent выше)
				if (!string.IsNullOrEmpty(item.ImageUrl) && item.ItemID == 0)
				{
					string imageUrlToUse = item.ImageUrl;
					string fileName = $"IconGS.{item.basketId}";

					// Проверяем кеш изображений предметов (загружается при использовании, не при инициализации)
					// Только если это валидный URL (начинается с http:// или https://)
					if ((item.ImageUrl.StartsWith("http://", StringComparison.OrdinalIgnoreCase) ||
					     item.ImageUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase)) &&
					    _imageCache != null)
					{
						// Проверяем, есть ли уже кешированное изображение
						string cachedItemImageId = _imageCache.GetItemImageId(fileName);
						if (!string.IsNullOrEmpty(cachedItemImageId))
						{
							// Используем кешированное изображение
							imageUrlToUse = cachedItemImageId;
						}
						else
						{
							// Если кеша нет, начинаем загрузку в фоне (для следующих разов)
							// Сейчас используем URL напрямую
							_imageCache.CacheItemImage(fileName, item.ImageUrl);
						}
					}

					if (!imageUrlToUse.Contains("http"))
						secondContainer.Add(new CuiElement
						{
							Parent = StoreLayer + $".BlockPanel.{index}",
							Components =
							{
								new CuiRawImageComponent {Png = imageUrlToUse},
								new CuiRectTransformComponent
									{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
							}
						});
					else
						secondContainer.Add(new CuiElement
						{
							Parent = StoreLayer + $".BlockPanel.{index}",
							Components =
							{
								new CuiRawImageComponent {Url = imageUrlToUse},
								new CuiRectTransformComponent
									{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
							}
						});
				}
			}

			// Количество товара в правом верхнем углу
			if (item.Amount > 1)
			{
				secondContainer.Add(new CuiLabel
					{
						RectTransform =
							{AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = "-50 -30", OffsetMax = "-8 -8"},
						Text =
						{
							Text = "x" + item.Amount, Align = TextAnchor.UpperRight,
							Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "1 1 1 0.5"
						}
					}, StoreLayer + $".BlockPanel.{index}", StoreLayer + $".BlockPanel.{index}.Amount");
			}

			if (_config.UI.TextShow)
			{
				// Название предмета прямо на карточке (внизу карточки, прижато к левому краю с отступами)
				secondContainer.Add(new CuiLabel
					{
						RectTransform =
							{AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "8 5", OffsetMax = "-8 25"},
						Text =
						{
							Text = item.Name, Align = TextAnchor.LowerLeft,
							Font = "robotocondensed-regular.ttf", FontSize = 12, Color = "0.8 0.8 0.8 1"
						}
					}, StoreLayer + $".BlockPanel.{index}", StoreLayer + $".BlockPanel.{index}.Name");
			}

			// Кнопка для клика - добавляем ПОСЛЕДНЕЙ, чтобы она была на переднем плане и перехватывала клики по всей карточке
			// В Rust UI порядок элементов определяет z-order - элементы, добавленные позже, отображаются поверх
			if (!CanRequestGetItem(Convert.ToInt32(item.basketId)))
			{
				// Если товар в обработке, показываем текст "Ожидание..." поверх кнопки
				secondContainer.Add(new CuiButton
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0"},
					Button =
					{
						FadeIn = 1f, Color = "0 0 0 0", // Прозрачный фон
						Close = StoreLayer + ".BlockPanel." + index + ".Open",
						Command = $"UI_GameStoresRUST take {index} {item.basketId}"
					},
					Text =
					{
						Text = Msg(player, MsgTakeWait), Align = TextAnchor.MiddleCenter,
						Font = "robotocondensed-bold.ttf", Color = "1 1 1 0.4", FontSize = 16
					}
				}, StoreLayer + ".BlockPanel." + index, StoreLayer + ".BlockPanel." + index + ".Open");
			}
			else
			{
				// Обычная кнопка для клика с hover эффектом
				secondContainer.Add(new CuiButton
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0"},
					Button =
					{
						Color = "0 0 0 0", // Полностью прозрачный цвет, чтобы не было видно фона
						FadeIn = 0.2f, // Плавное появление при наведении
						Close = StoreLayer + $".BlockPanel.{index}.Open",
						Command =
							$"UI_GameStoresRUST take {index} {item.basketId}"
					},
					Text = {Text = string.Empty}
				}, StoreLayer + $".BlockPanel.{index}", StoreLayer + $".BlockPanel.{index}.Open");
			}
		}

		private void ItemBlueprintImageUI(ref CuiElementContainer secondContainer, int index)
		{
			// Изображение чертежа уменьшено на 20% (отступы увеличены с 3 до 18)
			if (_config.Plugin.LoadSpriteImages)
			{
				secondContainer.Add(new CuiElement
				{
					Parent = StoreLayer + $".BlockPanel.{index}",
					Components =
					{
						new CuiImageComponent {ItemId = ItemManager.blueprintBaseDef.itemid},
						new CuiRectTransformComponent
							{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
					}
				});
			}
			else
			{
#if CARBON
				var blueprintImageComponent = new CuiRawImageComponent {Png = GetImage("blueprintbase")};
#else
				var blueprintImageComponent = ImageLibrary?.IsLoaded == true
					? new CuiRawImageComponent {Png = GetImage("blueprintbase")}
					: new CuiRawImageComponent {Url = "https://gamestores.app/img/games/rust/blueprintbase.png"};
#endif
				secondContainer.Add(new CuiElement
				{
					Parent = StoreLayer + $".BlockPanel.{index}",
					Components =
					{
						blueprintImageComponent,
						new CuiRectTransformComponent
							{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
					}
				});
			}
		}

		private void CreateStoreExitButtonUI(BasePlayer player, ref CuiElementContainer container)
		{
			// Кнопка закрытия должна закрывать окно и blur фон
			// Кнопка закрытия - используем команду closemenu, которая удалит все элементы
			container.AddRange(_config.UI.StoreExitButton.Get(Msg(player, MsgExit), "closemenu", StoreLayer + ".Window"));
		}

		private void CreateStoreUiContainer(ref CuiElementContainer container, BasePlayer player)
		{
			// Создаем blur фон на весь экран, чтобы было видно игру за окном
			// При клике на фон закрываем окно
			// Используем "Overlay" чтобы модальное окно было поверх всех элементов интерфейса игры
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0"},
				Image = {Material = "assets/content/ui/uibackgroundblur.mat", Color = "0 0 0 0.5"},
				CursorEnabled = true
			}, "Overlay", StoreLayer + ".Blur");

			// Кнопки для закрытия при клике на blur фон (вне окна)
			// Разделяем на 4 области вокруг окна, чтобы клик по самому окну не закрывал его
			// Верхняя область
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0 0.9", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "closemenu"},
				Text = {Text = ""}
			}, StoreLayer + ".Blur", StoreLayer + ".Blur.CloseTop");

			// Нижняя область
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 0.1", OffsetMin = "0 0", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "closemenu"},
				Text = {Text = ""}
			}, StoreLayer + ".Blur", StoreLayer + ".Blur.CloseBottom");

			// Левая область
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0 0.1", AnchorMax = "0.1 0.9", OffsetMin = "0 0", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "closemenu"},
				Text = {Text = ""}
			}, StoreLayer + ".Blur", StoreLayer + ".Blur.CloseLeft");

			// Правая область
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0.9 0.1", AnchorMax = "1 0.9", OffsetMin = "0 0", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "closemenu"},
				Text = {Text = ""}
			}, StoreLayer + ".Blur", StoreLayer + ".Blur.CloseRight");

			// Создаем основное окно по центру с изображением modal_background.png
			// Используем "Overlay" чтобы модальное окно было поверх всех элементов интерфейса игры
			container.Add(new CuiElement
			{
				Parent = "Overlay",
				Name = StoreLayer + ".Window",
				Components =
				{
					GetCachedImageComponent("modal_background", "https://storage.prostoj.store/plugin-file/modal_background.png"),
					new CuiRectTransformComponent
					{
						AnchorMin = _config.UI.Background.AnchorMin,
						AnchorMax = _config.UI.Background.AnchorMax,
						OffsetMin = _config.UI.Background.OffsetMin,
						OffsetMax = _config.UI.Background.OffsetMax
					},
					new CuiNeedsCursorComponent()
				}
			});

			// Фоновое изображение bg_right.png в правом нижнем углу модального окна
			// Создаем ПЕРВЫМ после основного окна, чтобы оно было за всеми остальными элементами (z-order)
			// ВАЖНО: Используем CuiElement без интерактивных компонентов, чтобы он не перехватывал клики
			// Ограничиваем область изображения только правой частью (начиная с 50% ширины), чтобы оно не перекрывало товары корзины слева
			var bgRightComponent = GetCachedImageComponent("bg_right", "https://storage.prostoj.store/plugin-file/bg_right.png");
			// Устанавливаем 80% прозрачности для изображения
			if (bgRightComponent is CuiRawImageComponent rawImage)
			{
				rawImage.Color = "1 1 1 0.2"; // 80% прозрачности (альфа = 0.2)
			}
			// Позиционируем изображение строго в правой части (начиная с 50% ширины), чтобы оно точно не перекрывало товары корзины (которые слева до 0.38)
			// ВАЖНО: Создаем ПЕРВЫМ после основного окна, чтобы оно было в самом низу z-order (за всеми остальными элементами)
			// В Rust UI порядок элементов в контейнере определяет z-order - элементы, добавленные позже, отображаются поверх
			container.Add(new CuiElement
			{
				Parent = StoreLayer + ".Window", // В правом нижнем углу модального окна
				Name = StoreLayer + ".Window.BgRight",
				Components =
				{
					bgRightComponent,
					new CuiRectTransformComponent
					{
						AnchorMin = "0.48 0", // Начинаем с 48% ширины (после PopularItemsPanel), чтобы не перекрывать карточки слева
						AnchorMax = "1 0", // До правого края
						OffsetMin = "0 0", // От низа
						OffsetMax = "0 512" // Высота изображения (512px вверх)
					}
					// НЕ добавляем CuiButtonComponent или другие интерактивные компоненты, чтобы не перехватывать клики
				}
			});

			// Создаем верхнюю навигационную панель в стиле скриншотов
			CreateTopNavigationBar(ref container, player);
		}

		private void CreateTopNavigationBar(ref CuiElementContainer container, BasePlayer player, string activeSection = "basket")
		{
			// Фон навигационной панели (прозрачный, так как modal_background уже используется)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0.92", AnchorMax = "1 1", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон, так как modal_background уже используется
			}, StoreLayer + ".Window", StoreLayer + ".TopNav");

			// Определяем активность кнопок
			bool isBasketActive = activeSection == "basket";
			bool isHelpActive = activeSection == "help";
			bool isSupportActive = activeSection == "support";

			// Отступы от краев и между кнопками
			float leftMargin = 0.02f; // Отступ слева 2% (уменьшен с 5%)
			float rightMargin = 0.05f; // Отступ справа 5%
			float buttonSpacing = 0.02f; // Отступ между кнопками 2%
			float buttonWidth = 0.15f; // Ширина кнопки 15% (было 20% и 15%)

			// Серый разделитель на всю ширину модального окна (создаем ПЕРВЫМ, чтобы был под всеми элементами)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 0.03", OffsetMax = "0 0"},
				Image = {Color = "0.561 0.561 0.561 1"} // Серый цвет, как у неактивного текста
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Divider");

			// Кнопка "Моя корзина" (без фона, с полоской внизу)
			string basketTextColor = isBasketActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin} 0.05", AnchorMax = $"{leftMargin + buttonWidth} 0.85", OffsetMax = "0 0"}, // Увеличена область для текста
				Button = {Color = "0 0 0 0", Command = isBasketActive ? "" : "UI_GameStoresRUST back"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgBasket), Align = TextAnchor.MiddleCenter,
					Font = isBasketActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = basketTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Basket");

			// Кнопка "Помощь" (без фона, с полоской внизу)
			string helpTextColor = isHelpActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin + buttonWidth + buttonSpacing} 0.05", AnchorMax = $"{leftMargin + buttonWidth * 2 + buttonSpacing} 0.85", OffsetMax = "0 0"}, // Увеличена область для текста
				Button = {Color = "0 0 0 0", Command = isHelpActive ? "" : "UI_GameStoresRUST help"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgHelp), Align = TextAnchor.MiddleCenter,
					Font = isHelpActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = helpTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Help");

			// Кнопка "Поддержка" (без фона, с полоской внизу)
			string supportTextColor = isSupportActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin + buttonWidth * 2 + buttonSpacing * 2} 0.05", AnchorMax = $"{leftMargin + buttonWidth * 3 + buttonSpacing * 2} 0.85", OffsetMax = "0 0"}, // Увеличена область для текста
				Button = {Color = "0 0 0 0", Command = isSupportActive ? "" : "UI_GameStoresRUST support"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgSupport), Align = TextAnchor.MiddleCenter,
					Font = isSupportActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = supportTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Support");

			// Фон menu_punkt_bg.png для активной кнопки "Моя корзина"
			if (isBasketActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Basket",
					Name = StoreLayer + ".TopNav.Basket.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin} 0", AnchorMax = $"{leftMargin + buttonWidth} 0.03", OffsetMax = "0 0"},
					Image = {Color = basketTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Basket.Underline");
			}

			// Фон menu_punkt_bg.png для активной кнопки "Помощь"
			if (isHelpActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Help",
					Name = StoreLayer + ".TopNav.Help.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin + buttonWidth + buttonSpacing} 0", AnchorMax = $"{leftMargin + buttonWidth * 2 + buttonSpacing} 0.03", OffsetMax = "0 0"},
					Image = {Color = helpTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Help.Underline");
			}

			// Фон menu_punkt_bg.png для активной кнопки "Поддержка"
			if (isSupportActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Support",
					Name = StoreLayer + ".TopNav.Support.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin + buttonWidth * 2 + buttonSpacing * 2} 0", AnchorMax = $"{leftMargin + buttonWidth * 3 + buttonSpacing * 2} 0.03", OffsetMax = "0 0"},
					Image = {Color = supportTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Support.Underline");
			}

			// Крестик для закрытия (справа, без фона, прижат к правому краю)
			float closeButtonWidth = 0.05f; // Ширина кнопки закрытия 5%
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{1 - closeButtonWidth} 0.1", AnchorMax = "1 0.9", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "closemenu"}, // Прозрачный фон
				Text =
				{
					Text = "✕", Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-bold.ttf", FontSize = 20, Color = "0.561 0.561 0.561 1"
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Close");
		}

		private void UpdateNavigationBar(BasePlayer player, string activeSection)
		{
			// Удаляем старые кнопки навигации и полоски
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Basket");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Basket.Bg");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Help");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Help.Bg");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Support");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Support.Bg");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Basket.Underline");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Help.Underline");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Support.Underline");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Divider");

			var container = new CuiElementContainer();

			// Определяем активность кнопок
			bool isBasketActive = activeSection == "basket";
			bool isHelpActive = activeSection == "help";
			bool isSupportActive = activeSection == "support";

			// Отступы от краев и между кнопками (те же, что в CreateTopNavigationBar)
			float leftMargin = 0.02f; // Отступ слева 2%
			float buttonSpacing = 0.02f; // Отступ между кнопками 2%
			float buttonWidth = 0.15f; // Ширина кнопки 15%

			// Серый разделитель на всю ширину модального окна (создаем ПЕРВЫМ, чтобы был под всеми элементами)
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 0.03", OffsetMax = "0 0"},
				Image = {Color = "0.561 0.561 0.561 1"} // Серый цвет, как у неактивного текста
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Divider");

			// Кнопка "Моя корзина" (без фона, с полоской внизу)
			string basketTextColor = isBasketActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin} 0.05", AnchorMax = $"{leftMargin + buttonWidth} 0.85", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = isBasketActive ? "" : "UI_GameStoresRUST back"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgBasket), Align = TextAnchor.MiddleCenter,
					Font = isBasketActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = basketTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Basket");

			// Кнопка "Помощь" (без фона, с полоской внизу)
			string helpTextColor = isHelpActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin + buttonWidth + buttonSpacing} 0.05", AnchorMax = $"{leftMargin + buttonWidth * 2 + buttonSpacing} 0.85", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = isHelpActive ? "" : "UI_GameStoresRUST help"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgHelp), Align = TextAnchor.MiddleCenter,
					Font = isHelpActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = helpTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Help");

			// Кнопка "Поддержка" (без фона, с полоской внизу)
			string supportTextColor = isSupportActive ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1";
			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = $"{leftMargin + buttonWidth * 2 + buttonSpacing * 2} 0.05", AnchorMax = $"{leftMargin + buttonWidth * 3 + buttonSpacing * 2} 0.85", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = isSupportActive ? "" : "UI_GameStoresRUST support"}, // Прозрачный фон
				Text =
				{
					Text = Msg(player, MsgSupport), Align = TextAnchor.MiddleCenter,
					Font = isSupportActive ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf",
					FontSize = 14,
					Color = supportTextColor
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Support");

			// Фон menu_punkt_bg.png для активной кнопки "Моя корзина"
			if (isBasketActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Basket",
					Name = StoreLayer + ".TopNav.Basket.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin} 0", AnchorMax = $"{leftMargin + buttonWidth} 0.03", OffsetMax = "0 0"},
					Image = {Color = basketTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Basket.Underline");
			}

			// Фон menu_punkt_bg.png для активной кнопки "Помощь"
			if (isHelpActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Help",
					Name = StoreLayer + ".TopNav.Help.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin + buttonWidth + buttonSpacing} 0", AnchorMax = $"{leftMargin + buttonWidth * 2 + buttonSpacing} 0.03", OffsetMax = "0 0"},
					Image = {Color = helpTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Help.Underline");
			}

			// Фон menu_punkt_bg.png для активной кнопки "Поддержка"
			if (isSupportActive)
			{
				// Изображение menu_punkt_bg.png за текстом кнопки (по центру, на всю высоту кнопки)
				container.Add(new CuiElement
				{
					Parent = StoreLayer + ".TopNav.Support",
					Name = StoreLayer + ".TopNav.Support.Bg",
					Components =
					{
						GetCachedImageComponent("menu_punkt_bg", "https://storage.prostoj.store/plugin-file/menu_punkt_bg.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0", // От левого края кнопки, от низа кнопки (0)
							AnchorMax = "1 1", // До правого края кнопки, до верха кнопки (1)
							OffsetMin = "0 0", // Без смещения
							OffsetMax = "0 0" // Без смещения
						}
					}
				});

				// Полоска внизу кнопки в цвет текста активной кнопки (позиционируется относительно TopNav, как разделитель)
				container.Add(new CuiPanel
				{
					RectTransform = {AnchorMin = $"{leftMargin + buttonWidth * 2 + buttonSpacing * 2} 0", AnchorMax = $"{leftMargin + buttonWidth * 3 + buttonSpacing * 2} 0.03", OffsetMax = "0 0"},
					Image = {Color = supportTextColor} // Цвет текста активной кнопки
				}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Support.Underline");
			}

			CuiHelper.AddUi(player, container);
		}

		private void CreateDescriptionBlock(ref CuiElementContainer container, BasePlayer player)
		{
			// Контейнер блока описания (прижат к верхнему меню)
			// Цвет меню: 0.1 0.1 0.1 0.95, на 40% светлее: 0.1 * 1.4 = 0.14
			// Используем непрозрачный фон для лучшей видимости
			container.Add(new CuiPanel
			{
				RectTransform = {AnchorMin = "0 0.88", AnchorMax = "1 0.92", OffsetMax = "0 0"},
				Image = {Color = "0 0 0 0"} // Прозрачный фон, так как modal_background уже используется
			}, StoreLayer + ".Window", StoreLayer + ".DescriptionBlock");

			// Текст описания по центру (многострочный)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"}, // На весь блок
				Text =
				{
					Text = Msg(player, MsgBasketDescription),
					Align = TextAnchor.MiddleCenter, // По центру
					Font = "robotocondensed-regular.ttf",
					FontSize = 12, // Немного уменьшил шрифт для двух строк
					Color = "0.7 0.7 0.7 1"
				}
			}, StoreLayer + ".DescriptionBlock");
		}

		private void UpdatePaginationInNavBar(BasePlayer player, int page, int totalItems)
		{
			var totalPages = (int)Math.Ceiling((double)totalItems / basketItemsPerPage);
			var paginationText = $"СТРАНИЦА {page + 1} ИЗ {totalPages}";

			var container = new CuiElementContainer();

			// Удаляем старую пагинацию (удаляем каждый элемент отдельно)
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Text");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Prev");
			CuiHelper.DestroyUi(player, StoreLayer + ".TopNav.Pagination.Next");

			// Текст пагинации (прижат к крестику справа)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0.7 0", AnchorMax = "0.82 1", OffsetMax = "0 0"},
				Text =
				{
					Text = paginationText, Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-regular.ttf", FontSize = 14, Color = "0.561 0.561 0.561 1"
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Pagination.Text");

			// Отступы для кнопок пагинации
			float buttonSpacing = 0.01f; // Отступ между кнопками 1%
			float buttonWidth = 0.04f; // Ширина кнопки 4% (уменьшено)
			float buttonVerticalMargin = 0.1f; // Отступ по вертикали 10%

			// Кнопка "Назад" (уменьшена, с отступами)
			float prevStart = 0.82f;
			container.Add(new CuiButton
			{
				RectTransform =
					{AnchorMin = $"{prevStart} {buttonVerticalMargin}", AnchorMax = $"{prevStart + buttonWidth} {1 - buttonVerticalMargin}", OffsetMax = "0 0"},
				Button =
				{
					Color = page > 0 ? "1 0.38 0.204 0.25" : "0.180 0.102 0.231 1",
					Command = page > 0 ? $"UI_GameStoresRUST page {page - 1}" : string.Empty
				},
				Text =
				{
					Text = "◄", Color = page > 0 ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1",
					Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf", FontSize = 14 // Уменьшен размер
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Pagination.Prev");

			// Кнопка "Вперед" (уменьшена, с отступом от предыдущей)
			var hasNextPage = (page + 1) * basketItemsPerPage < totalItems;
			float nextStart = prevStart + buttonWidth + buttonSpacing;
			container.Add(new CuiButton
			{
				RectTransform =
					{AnchorMin = $"{nextStart} {buttonVerticalMargin}", AnchorMax = $"{nextStart + buttonWidth} {1 - buttonVerticalMargin}", OffsetMax = "0 0"},
				Button =
				{
					Color = hasNextPage ? "1 0.38 0.204 0.25" : "0.180 0.102 0.231 1",
					Command = hasNextPage ? $"UI_GameStoresRUST page {page + 1}" : string.Empty
				},
				Text =
				{
					Text = "►", Color = hasNextPage ? "1 0.38 0.204 1" : "0.561 0.561 0.561 1",
					Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf", FontSize = 14 // Уменьшен размер
				}
			}, StoreLayer + ".TopNav", StoreLayer + ".TopNav.Pagination.Next");

			CuiHelper.AddUi(player, container);
		}

		private void CreateBlockPanel(ref CuiElementContainer container, BasePlayer player)
		{
			// Создаем панель для популярных товаров (правая часть) ПЕРВОЙ, чтобы она была внизу z-order
			// Это важно, чтобы она не перекрывала карточки корзины
			container.Add(new CuiPanel
			{
				RectTransform =
				{
					AnchorMin = _config.UI.PopularItemsPanel.AnchorMin,
					AnchorMax = _config.UI.PopularItemsPanel.AnchorMax,
					OffsetMin = _config.UI.PopularItemsPanel.OffsetMin,
					OffsetMax = _config.UI.PopularItemsPanel.OffsetMax
				},
				Image = {Color = "0 0 0 0"} // Прозрачный фон
			}, StoreLayer + ".Window", StoreLayer + ".PopularItemsPanel");

			// Создаем панель для карточек товаров корзины (левая часть) ПОСЛЕ PopularItemsPanel, чтобы она была поверх
			container.Add(new CuiPanel
			{
				RectTransform =
				{
					AnchorMin = _config.UI.BlockPanel.AnchorMin,
					AnchorMax = _config.UI.BlockPanel.AnchorMax,
					OffsetMin = _config.UI.BlockPanel.OffsetMin,
					OffsetMax = _config.UI.BlockPanel.OffsetMax
				},
				Image = {Color = "0 0 0 0"} // Прозрачный фон, так как modal_background уже используется
			}, StoreLayer + ".Window", StoreLayer + ".BlockPanel");

			// Заголовок для товаров корзины - прижат к левому краю панели с небольшим отступом
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0.92", AnchorMax = "0 0.98", OffsetMin = "10 0", OffsetMax = "200 0"},
				Text = {Text = "МОИ ТОВАРЫ", Align = TextAnchor.MiddleLeft, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "1 0.38 0.204 1"}
			}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Title");

			// Заголовок для популярных товаров - прижат к правому краю панели с небольшим отступом
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "1 0.92", AnchorMax = "1 0.98", OffsetMin = "-200 0", OffsetMax = "-10 0"},
				Text = {Text = "МОМЕНТАЛЬНАЯ ПОКУПКА", Align = TextAnchor.MiddleRight, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "1 0.38 0.204 1"}
			}, StoreLayer + ".PopularItemsPanel", StoreLayer + ".PopularItemsPanel.Title");

			container.Add(_config.UI.RequestProcessingTitle.GetText(Msg(player, MsgRequestProcessing),
				StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Text"));
		}

		private void InitializeIcon(BasePlayer player)
		{
			UpdateUI(player, container =>
			{
				if (_config.UI.BucketURL.Contains("http"))
					CreateBucketIconWithImageLibrary(ref container);
				else
					CreateBucketIconWithoutImageLibrary(ref container);
			});
		}

		private void CreateBucketIconWithImageLibrary(ref CuiElementContainer container)
		{
#if CARBON
			var bucketImageComponent = new CuiRawImageComponent {Png = GetImage(ImageBucket)};
#else
			var bucketImageComponent = ImageLibrary is {IsLoaded: true}
				? new CuiRawImageComponent {Png = GetImage(ImageBucket)}
				: new CuiRawImageComponent {Url = _config.UI.BucketURL};
#endif

			container.Add(new CuiElement
			{
				Parent = "Overlay",
				Name = IconLayer,
				DestroyUi = IconLayer,
				Components =
				{
					bucketImageComponent,
					new CuiRectTransformComponent
					{
						AnchorMin = _config.UI.BucketPosition.AnchorMin,
						AnchorMax = _config.UI.BucketPosition.AnchorMax,
						OffsetMin = _config.UI.BucketPosition.OffsetMin,
						OffsetMax = _config.UI.BucketPosition.OffsetMax
					}
				}
			});

			container.Add(new CuiButton
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
				Button = {Color = "0 0 0 0", Command = "chat.say /store"},
				Text = {Text = string.Empty}
			}, IconLayer);
		}

		private void CreateBucketIconWithoutImageLibrary(ref CuiElementContainer container)
		{
			container.Add(new CuiButton
			{
				RectTransform =
				{
					AnchorMin = _config.UI.BucketPosition.AnchorMin,
					AnchorMax = _config.UI.BucketPosition.AnchorMax,
					OffsetMin = _config.UI.BucketPosition.OffsetMin,
					OffsetMax = _config.UI.BucketPosition.OffsetMax
				},
				Button = {Color = "1 1 1 0.6", Sprite = "assets/icons/open.png", Command = "chat.say /store"},
				Text = {Text = string.Empty}
			}, "Overlay", IconLayer, IconLayer);
		}

		private void UI_RecountPosition(out double xSwitch, out double ySwitch, int count, int max)
		{
			var stringAmount = Math.Ceiling((double) max / _config.UI.ItemOnString);
			var currentString = Math.Floor((double) count / _config.UI.ItemOnString);
			var currentPosition = count % _config.UI.ItemOnString;

			// Позиционируем товары слева, начиная с отступа от левого края
			var leftMargin = 10; // Отступ от левого края в пикселях
			var topYPosition = 0 + (float) stringAmount / 2 * _config.UI.ItemSide +
			                   ((float) stringAmount / 2 - 1) * _config.UI.ItemMargin * 2;
			var topXPosition = leftMargin; // Начинаем с левого края с небольшим отступом

			// Убрали отступ для текста под карточкой, так как название теперь на карточке
			// Расстояние между товарами увеличено в 2 раза
			var curYPosition = topYPosition - currentString * _config.UI.ItemSide - currentString *
				_config.UI.ItemMargin * 2;
			var curXPosition = topXPosition + currentPosition * _config.UI.ItemSide +
			                   currentPosition * _config.UI.ItemMargin * 2;

			xSwitch = curXPosition;
			ySwitch = curYPosition - 20; // Смещаем товары немного ниже
		}

		private void UI_RecountPositionPopular(out double xSwitch, out double ySwitch, int count, int max, int itemsPerRow)
		{
			var stringAmount = Math.Ceiling((double) max / itemsPerRow);
			var currentString = Math.Floor((double) count / itemsPerRow);
			var currentPosition = count % itemsPerRow;

			// Позиционируем товары справа, начиная с отступа от правого края панели PopularItemsPanel
			var rightMargin = 10; // Отступ от правого края панели в пикселях
			var topYPosition = 0 + (float) stringAmount / 2 * _config.UI.ItemSide +
			                   ((float) stringAmount / 2 - 1) * _config.UI.ItemMargin * 2;

			var curYPosition = topYPosition - currentString * _config.UI.ItemSide - currentString *
				_config.UI.ItemMargin * 2;

			// Позиция X от правого края: правая колонка (currentPosition = 1) ближе к правому краю
			// Для правой колонки (currentPosition = 1): отступ = rightMargin
			// Для левой колонки (currentPosition = 0): отступ = rightMargin + ItemSide + ItemMargin*2
			// Но нам нужно позиционировать от левого края панели, поэтому вычисляем позицию от левого края
			// Ширина двух колонок: 2 * ItemSide + ItemMargin*2
			// Позиция от левого края = ширина панели - (отступ от правого края + ширина товара)
			// Но проще: позиция от левого края = ширина панели - (rightMargin + (itemsPerRow - currentPosition) * (ItemSide + ItemMargin*2) + ItemSide)
			// Но мы не знаем ширину панели в пикселях, поэтому используем AnchorMin = "1 1" и OffsetMin с отрицательными значениями
			// Возвращаем отрицательные значения для OffsetMin (от правого края)
			var offsetFromRight = rightMargin + (itemsPerRow - 1 - currentPosition) * (_config.UI.ItemSide + _config.UI.ItemMargin * 2);

			// xSwitch будет использован как отрицательное значение в OffsetMin для позиционирования от правого края
			// OffsetMin.x = -offsetFromRight - ItemSide (левая граница карточки от правого края)
			xSwitch = -offsetFromRight - _config.UI.ItemSide;
			ySwitch = curYPosition - 20; // Смещаем товары немного ниже
		}

		#endregion

		#region UI.Helpers

		private static void UpdateUI(BasePlayer player, Action<CuiElementContainer> callback)
		{
			if (player == null) return;

			var container = new CuiElementContainer();

			callback?.Invoke(container);

			CuiHelper.AddUi(player, container);
		}

		#endregion

		#endregion

		#region Utils

		#region Player Basket

		#region Classes

		private class PlayerBasket
		{
			public Dictionary<int, WItem> BasketItems = new();

			public PlayerBasket(List<object> data)
			{
				if (data == null)
				{
					Interface.Oxide.LogError("[PlayerBasket] Data is null");
					return;
				}

				foreach (var productObj in data)
				{
					if (productObj == null)
					{
						Interface.Oxide.LogWarning("[PlayerBasket] Product object is null, skipping");
						continue;
					}

					if (!(productObj is Dictionary<string, object> product))
					{
						Interface.Oxide.LogWarning($"[PlayerBasket] Product is not Dictionary<string, object>, type: {productObj.GetType().Name}, skipping");
						continue;
					}

					if (!product.TryGetValue("basketId", out var basketIdObj) || basketIdObj == null)
					{
						Interface.Oxide.LogWarning("[PlayerBasket] basketId is missing or null, skipping");
						continue;
					}

					if (!int.TryParse(basketIdObj.ToString(), out var basketId))
					{
						Interface.Oxide.LogWarning($"[PlayerBasket] Cannot parse basketId: {basketIdObj}, skipping");
						continue;
					}

					try
					{
						var item = new WItem(product);

						if (item.IsValid)
						{
							// Проверяем, есть ли isBlocked в данных от API
							bool apiIsBlocked = false;
							double apiLeftTime = 0;
							if (product.TryGetValue("isBlocked", out var isBlockedObj))
							{
								apiIsBlocked = Convert.ToBoolean(isBlockedObj);
							}
							if (product.TryGetValue("leftTime", out var leftTimeObj))
							{
								double.TryParse(leftTimeObj?.ToString(), out apiLeftTime);
							}

							// Проверяем вайп-блок через плагин (дополнительная проверка)
							var leftTime = Instance?.WB_GetLeftTime(item.productId, item.ItemID, item.IsBlueprint) ?? 0;

							if (leftTime > 0 || apiIsBlocked)
							{
								item.IsBlocked = true;
								item.LeftTime = leftTime > 0 ? leftTime : apiLeftTime;
								// Логируем только заблокированные предметы
								Interface.Oxide.LogWarning($"[PlayerBasket] ITEM IS BLOCKED. basketId={basketId}, productId={item.productId}, itemId={item.ItemID}, leftTime={item.LeftTime}, apiIsBlocked={apiIsBlocked}, apiLeftTime={apiLeftTime}, pluginLeftTime={leftTime}");
							}
							else
							{
								item.IsBlocked = false;
								item.LeftTime = 0;
							}

							BasketItems[basketId] = item;
						}
					}
					catch (Exception ex)
					{
						Interface.Oxide.LogError($"[PlayerBasket] Error creating WItem for basketId {basketId}: {ex.Message}");
						Interface.Oxide.LogError($"[PlayerBasket] Product data: {JsonConvert.SerializeObject(product)}");
					}
				}
			}

			public void Clear()
			{
				BasketItems.Clear();
			}

			public List<WItem> GetItems(int page, int count)
			{
				return new List<WItem>(BasketItems.Values.Skip(page * count).Take(count));
			}

			public int GetCount()
			{
				return BasketItems.Count;
			}
		}

		#endregion

		#endregion

		#region Blocks

		private bool NE_IsCombatBlocked(BasePlayer player)
		{
			return Convert.ToBoolean(NoEscape?.Call("IsCombatBlocked", player));
		}

		private bool NE_IsRaidBlocked(BasePlayer player)
		{
			return Convert.ToBoolean(NoEscape?.Call("IsRaidBlocked", player));
		}

		private double WB_GetLeftTime(string productId, int itemID, bool isBlueprint)
		{
			return Convert.ToDouble(GameStoresWipeBlock?.Call("GetLeftTime", productId, itemID, isBlueprint));
		}

		private bool IsBlockedByForeignTCNearFoundationOrCeiling(BasePlayer player, float r = 15f)
		{
			// 1) Должен действовать общий билд-блок (иначе дальше не проверяем)
			if (!player.IsBuildingBlocked())
				return false;

			// 2) Ищем ближайший фундамент/потолок
			var blocks = new List<BuildingBlock>();
			Vis.Entities(player.transform.position, r, blocks);

			BuildingBlock nearest = null;
			float best = float.MaxValue;

			foreach (var b in blocks)
			{
				var name = b?.ShortPrefabName;
				if (string.IsNullOrEmpty(name)) continue;

				// foundation / foundation.triangle / floor / floor.triangle
				bool isFoundationOrFloor = name.Contains("foundation") || name.Contains("floor");
				if (!isFoundationOrFloor) continue;

				float d = Vector3.Distance(player.transform.position, b.transform.position);
				if (d < best)
				{
					best = d;
					nearest = b;
				}
			}

			if (nearest == null)
				return false; // рядом нет фундамента/потолка → скорее всего РТ или просто пусто

			// 3) Шкаф, контролирующий этот блок
			var priv = nearest.GetBuildingPrivilege();
			if (priv != null)
				return !priv.IsAuthed(player); // чужой шкаф = нет авторизации

			// 4) Fallback: если по какой-то причине привилегию блока не получили,
			//   ищем ближайший шкаф вокруг блока и проверяем авторизацию
			var tcs = new List<BuildingPrivlidge>();
			Vis.Entities(nearest.transform.position, 30f, tcs); // разумный радиус до TC

			BuildingPrivlidge closestTc = null;
			float bestTc = float.MaxValue;
			foreach (var tc in tcs)
			{
				float d = Vector3.Distance(nearest.transform.position, tc.transform.position);
				if (d < bestTc)
				{
					bestTc = d;
					closestTc = tc;
				}
			}

			if (closestTc == null)
				return false; // рядом вообще нет шкафов → не считаем чужой зоной (например, РТ)

			return !closestTc.IsAuthed(player);
		}

		#endregion

		#region Plugin Loading

		private void LoadHeaderParams()
		{
			reqHeaders.Add("X-Plugin-Version", Version.ToString());

			// Добавляем storeId только если он установлен (не UNDEFINED)
			if (_config?.API != null && _config.API.ShopID != "UNDEFINED" && !string.IsNullOrEmpty(_config.API.ShopID))
			{
			reqHeaders.Add("storeId", _config.API.ShopID);
			}

			// Добавляем IP и port в headers (приоритет выше, чем в query string)
			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			reqHeaders.Add("serverIp", serverIp);
			reqHeaders.Add("serverPort", serverPort.ToString());
		}

		private void RegisterCommands()
		{
			AddCovalenceCommand(_config.Plugin.Commands, nameof(CmdChatStore));
			// Временно отключено
			// AddCovalenceCommand("help", nameof(CmdChatHelp));
		}

		private bool InitializeServerSettings()
		{
			// ServerID больше не нужен, сервер определяется по IP и порту
				StartInstantTimer();
			return true;
		}

		private void InitializeTimeoutCommands()
		{
			ListTimeOutCommand[0] = new Dictionary<ulong, int>();
			ListTimeOutCommand[1] = new Dictionary<ulong, int>();
			ListTimeOutCommand[2] = new Dictionary<ulong, int>();

			var totalTick = 0;
			var tick = 0;

			timer.Repeat(2, 0, () =>
			{
				if (totalTick == 30)
				{
					totalTick = 0;
					ListBannedCommandUserID.Clear();
				}

				ListTimeOutCommand[0].Clear();
				if (tick == 3 || tick == 6) ListTimeOutCommand[1].Clear();

				if (tick == 6)
				{
					tick = 0;
					ListTimeOutCommand[2].Clear();
				}

				tick++;
				totalTick++;
			});
		}

		private void InitializePlayers()
		{
			for (var i = 0; i < BasePlayer.activePlayerList.Count; i++)
				OnPlayerConnected(BasePlayer.activePlayerList[i]);
		}

		private IEnumerator WaitForLoad()
		{
			while (Instance?.HasImage(ImageBucket) != true)
			{
				PrintError("Image of bucket is loading!");
				yield return new WaitForSeconds(1);
			}

			PrintWarning("Image of bucket loaded correctly!");
			InitializePlayers();

			yield return 0;
		}

		#endregion

		#region Image Loading

		#region Image Helpers

		private string GetImage(string name)
		{
#if CARBON
			return imageDatabase.GetImageString(name);
#else
			return Convert.ToString(ImageLibrary?.Call("GetImage", name));
#endif
		}

		private void AddImage(string url, string fileName, ulong imageId = 0)
		{
			// Проверяем, что url является валидным URL (начинается с http:// или https://)
			// Если url является числом (item ID), не пытаемся загружать его как URL
			if (!string.IsNullOrEmpty(url) &&
			    !url.StartsWith("http://", StringComparison.OrdinalIgnoreCase) &&
			    !url.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
			{
				// url не является валидным URL, пропускаем загрузку
				return;
			}

			// Если это изображение предмета (начинается с "IconGS."), используем кеширование
			if (fileName.StartsWith("IconGS.") && _imageCache != null)
			{
				// Сначала проверяем, есть ли уже кешированное изображение
				string cachedId = _imageCache.GetItemImageId(fileName);
				if (!string.IsNullOrEmpty(cachedId))
				{
					// Используем кешированное изображение через ImageLibrary
#if CARBON
					imageDatabase.Queue(true, new Dictionary<string, string>
					{
						[fileName] = cachedId
					});
#else
					ImageLibrary?.Call("AddImage", cachedId, fileName, imageId);
#endif
					return;
				}

				// Если кеша нет, начинаем кеширование в фоне (для следующих разов)
				_imageCache.CacheItemImage(fileName, url);
			}

			// Обычная загрузка через ImageLibrary (используем URL, так как кеш еще не готов)
#if CARBON
			imageDatabase.Queue(true, new Dictionary<string, string>
			{
				[fileName] = url
			});
#else
			ImageLibrary?.Call("AddImage", url, fileName, imageId);
#endif
		}

		private bool HasImage(string name)
		{
#if CARBON
			return Convert.ToBoolean(imageDatabase.HasImage(name));
#else
			return Convert.ToBoolean(ImageLibrary?.Call("HasImage", name));
#endif
		}

		private ICuiComponent GetCardImageComponent(string imageName, string fallbackUrl)
		{
			// Сначала пытаемся использовать кешированное изображение из нового кеша
			string cachedImageId = GetCachedImageId(imageName);
			if (string.IsNullOrEmpty(cachedImageId) && _imageCache != null)
			{
				// Пробуем напрямую по имени в кеше (например, "light")
				if (_imageCache.IsImageLoaded(imageName))
				{
					cachedImageId = _imageCache.GetImageId(imageName);
				}
			}

			if (!string.IsNullOrEmpty(cachedImageId))
			{
				return new CuiRawImageComponent {Png = cachedImageId};
			}

#if CARBON
			if (HasImage(imageName))
			{
				return new CuiRawImageComponent {Png = GetImage(imageName)};
			}
			return new CuiRawImageComponent {Url = fallbackUrl};
#else
			if (ImageLibrary?.IsLoaded == true && HasImage(imageName))
			{
				return new CuiRawImageComponent {Png = GetImage(imageName)};
			}
			return new CuiRawImageComponent {Url = fallbackUrl};
#endif
		}

		private ICuiComponent GetCachedImageComponent(string imageName, string fallbackUrl)
		{
			// Если imageName уже является именем в кеше (например, "card_gray"), используем его напрямую
			// Иначе пытаемся использовать как константу
			string cachedImageId = GetCachedImageId(imageName);
			if (string.IsNullOrEmpty(cachedImageId) && _imageCache != null)
			{
				// Пробуем напрямую по имени в кеше
				if (_imageCache.IsImageLoaded(imageName))
				{
					cachedImageId = _imageCache.GetImageId(imageName);
				}
			}

			// Для bg_right используем 80% прозрачности, для остальных - белый цвет
			if (!string.IsNullOrEmpty(cachedImageId))
			{
				if (imageName == "bg_right")
				{
					return new CuiRawImageComponent {Png = cachedImageId, Color = "1 1 1 0.2"}; // 80% прозрачности для bg_right
				}
				return new CuiRawImageComponent {Png = cachedImageId, Color = "1 1 1 1"}; // Белый цвет для остальных
			}
			if (imageName == "bg_right")
			{
				return new CuiRawImageComponent {Url = fallbackUrl, Color = "1 1 1 0.2"}; // 80% прозрачности для bg_right
			}
			return new CuiRawImageComponent {Url = fallbackUrl, Color = "1 1 1 1"}; // Белый цвет для остальных
		}

		#endregion

		private const int maxImageCheck = 3;
		private int TryImageCounter;

		private void InitializeImages()
		{
			// Инициализируем кеш изображений только один раз
			if (_imagesCacheInitialized)
				return;

			_imagesCacheInitialized = true;
			// Инициализируем кеш изображений (не выводим сообщения для уменьшения шума в логах)
			_imageCache = new ImageCache(this);
			_imageCache.DownloadImages();

#if CARBON
			imageDatabase.Queue(true, new Dictionary<string, string>
			{
				["NONE"] = "https://gspics.org/images/2024/03/29/0NOj7D.png",
				["LOADING"] = "https://gspics.org/images/2024/03/29/0NOxFI.png"
			});
#else
			if (ImageLibrary?.IsLoaded == true)
			{
				NoImageID = GetImage("NONE");
				LoadingImageID = GetImage("LOADING");
			}
#endif
		}

		private void InitializeBucketImage()
		{
			AddImage(_config.UI.BucketURL, ImageBucket);

			_loadingCoroutine = ServerMgr.Instance.StartCoroutine(WaitForLoad());
		}

		#endregion

		#region Take Item

		private void ProcessTake(BasePlayer player, Dictionary<string, object> obj)
		{
			var itemInfo = new WItem(obj);

			try
			{
				// Логирование попытки выдачи предмета
				LogAction(player, $"ProcessTake: Attempting to issue item. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}, isItem={itemInfo.IsItem}, isCommand={itemInfo.IsCommand}, isBlueprint={itemInfo.IsBlueprint}, isBlocked={itemInfo.IsBlocked}, leftTime={itemInfo.LeftTime}", true);

				if (!itemInfo.IsValid)
				{
					LogAction(player, $"ProcessTake: Item is NOT valid. basketId={itemInfo.basketId}, productId={itemInfo.productId}", true);
					return;
				}

				// Проверяем вайп блок перед выдачей
				if (itemInfo.IsBlocked && itemInfo.LeftTime > 0)
				{
					LogAction(player, $"ProcessTake: BLOCKED - Item is blocked by wipe block. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}, leftTime={itemInfo.LeftTime}", true);
					ShowNotify(player, $"Предмет временно заблокирован вайп блоком. Осталось времени: {FormatTime(player, itemInfo.LeftTime)}", true);
					return;
				}

				if (itemInfo.IsItem)
				{
					LogAction(player, $"ProcessTake: Processing as ITEM. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}, amount={itemInfo.Amount}", true);
					ProcessItem(player, itemInfo);
				}

				if (itemInfo.IsCommand)
				{
					LogAction(player, $"ProcessTake: Processing as COMMAND. basketId={itemInfo.basketId}, productId={itemInfo.productId}", true);
					ProcessCommand(player, itemInfo);
				}

				if (itemInfo.IsBlueprint)
				{
					LogAction(player, $"ProcessTake: Processing as BLUEPRINT. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}", true);
					ProcessBlueprint(player, itemInfo);
				}
			}
			catch (Exception e)
			{
				LogAction(player, $"ProcessTake: ERROR - Exception occurred. basketId={itemInfo.basketId}, error={e.Message}", true);
				HandleItemProcessingError(player, itemInfo, e);
			}
		}

		private void ProcessItem(BasePlayer player, WItem itemInfo)
		{
			LogAction(player, $"ProcessItem: Starting. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}, amount={itemInfo.Amount}, isBlocked={itemInfo.IsBlocked}, leftTime={itemInfo.LeftTime}", true);

			if (itemInfo.itemDefinition == null)
			{
				LogAction(player, $"ProcessItem: ERROR - itemDefinition is null. basketId={itemInfo.basketId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}", true);
				PrintError($"GameStoresRUST: itemDefinition is null for item {itemInfo.Name} (ItemID: {itemInfo.ItemID})");
				ShowNotify(player, "Ошибка: не удалось создать предмет. Товар добавлен в корзину.", true);
				return;
			}

			var item = ItemManager.Create(itemInfo.itemDefinition, itemInfo.Amount);

			if (item == null)
			{
				LogAction(player, $"ProcessItem: ERROR - Failed to create item. basketId={itemInfo.basketId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}", true);
				PrintError($"GameStoresRUST: Failed to create item {itemInfo.Name} (ItemID: {itemInfo.ItemID})");
				ShowNotify(player, "Ошибка: не удалось создать предмет. Товар добавлен в корзину.", true);
				return;
			}

			LogAction(player, $"ProcessItem: Item created successfully. basketId={itemInfo.basketId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}, amount={itemInfo.Amount}, itemInfoId={item.info.itemid}", true);

			if (!player.inventory.GiveItem(item))
			{
				LogAction(player, $"ProcessItem: ERROR - Failed to give item to inventory. basketId={itemInfo.basketId}, itemId={itemInfo.ItemID}", true);
				HandleItemGiveError(player, item, itemInfo);
			}
			else
			{
				LogAction(player, $"ProcessItem: SUCCESS - Item given to player. basketId={itemInfo.basketId}, productId={itemInfo.productId}, itemId={itemInfo.ItemID}, name={itemInfo.Name}, amount={itemInfo.Amount}", true);
				// Уведомление убрано - статус показывается в карточке
			}
		}

		private void ProcessInstantCommand(WItem itemInfo)
		{
			ExecuteCommands(null, itemInfo.commands, itemInfo.SteamID.ToString());
		}

		private void ProcessCommand(BasePlayer player, WItem itemInfo)
		{
			if (player == null)
			{
				ExecuteCommands(null, itemInfo.commands, itemInfo.SteamID.ToString());
				return;
			}

			ExecuteCommands(player, itemInfo.commands, player.UserIDString, player.displayName);

			// Уведомление убрано - статус показывается в карточке
		}

		private void ExecuteCommands(BasePlayer player, List<object> commands, string steamId, string username = null)
		{
			foreach (string command in commands)
			{
				var selfCommand = ReplacePlaceholders(command, steamId, username);

				ExecuteCommandSequence(selfCommand, player);
			}
		}

		private string ReplacePlaceholders(string command, string steamId, string username = null)
		{
			return command
				.Replace("%steamid%", steamId, StringComparison.OrdinalIgnoreCase)
				.Replace("%username%", username ?? string.Empty, StringComparison.OrdinalIgnoreCase);
		}

		private void ProcessBlueprint(BasePlayer player, WItem itemInfo)
		{

			var create = ItemManager.Create(ItemManager.blueprintBaseDef);
			create.blueprintTarget = itemInfo.itemDefinition.itemid;

			if (!player.inventory.GiveItem(create))
			{
				HandleItemGiveError(player, create, itemInfo);
			}
			else
			{
				// Уведомление убрано - статус показывается в карточке
			}
		}

		private void ExecuteCommandSequence(string command, BasePlayer player = null)
		{
			Server.Command(command);
		}

		#endregion

		#region Instant

		private void StartInstantTimer()
		{
			_checkInstant = timer.Every(60, HandleInstantCommands);
		}

		private void DestroyInstantTimer()
		{
			_checkInstant?.Destroy();
		}

		private void TakeInstant(int basketID)
		{
			RequestGetItem(basketID, true);
		}

		#region Handle

		private void HandleInstantCommands()
		{
			Request("baskets.instantCommands", null, (code, response) =>
			{
				switch (code)
				{
					case 200:
						HandleInstantRequestSuccess(response);
						break;
					default:
						break;
				}
			});
		}

		private void HandleInstantRequestSuccess(string response)
		{
			var firstInfo =
				JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
			if (firstInfo.ContainsKey("result"))
			{
				if (firstInfo["result"].ToString() == "success")
				{
					var data = firstInfo["data"] as List<object>;
					if (data == null)
					{
						return;
					}

					var i = 1;
					foreach (Dictionary<string, object> product in data)
					{
						if (!product.TryGetValue("basketId", out var basketId)) continue;

						var basketID = Convert.ToInt32(basketId);

						if (!_config.Plugin.ExecuteInstantCommandsWhenNotInServer)
							if (product.TryGetValue("steamId", out var value))
							{
								var steamId = Convert.ToString(value);
								if (!string.IsNullOrEmpty(steamId) && steamId.IsSteamId() &&
								    covalence.Players.FindPlayerById(steamId) == null)
									continue;
							}

						timer.In(i, () => TakeInstant(basketID));

						i++;
					}

					return;
				}

				if (firstInfo["result"].ToString() == "fail")
					if (firstInfo.TryGetValue("message", out var value))
					{
						var message = Convert.ToString(value);
						if (!string.IsNullOrEmpty(message))
						{
							return;
						}
					}
			}
		}

		private void HandleTakeInstantResponse(int code, string response, int basketID)
		{
			switch (code)
			{
				case 0:
					PrintError("Api does not responded to a request");
					break;
				case 200:
					HandleTakeInstantSuccess(response, basketID);
					break;
				case 404:
					break;
			}
		}

		private void HandleTakeInstantSuccess(string response, int basketID)
		{
			var responseData =
				JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
			if (responseData == null)
			{
				return;
			}

			if (!responseData.TryGetValue("data", out var value))
			{
				return;
			}

			if (value is not Dictionary<string, object> data) return;

			var itemInfo = new WItem(data);
			if (!itemInfo.IsValid)
			{
				return;
			}

			if (covalence?.Players?.FindPlayerById(itemInfo.SteamID.ToString()) == null)
			{
				return;
			}

			var leftTime = WB_GetLeftTime(itemInfo.productId,
				itemInfo.ItemID, itemInfo.IsBlueprint);
			if (leftTime > 0)
			{
				return;
			}

			Request("baskets.makeIssued", new Dictionary<string, string>
			{
				{"steamId", itemInfo.SteamID.ToString()},
				{"basketId", basketID.ToString()}
			}, (code, newResponse) =>
			{
				if (code != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() !=
				    "success")
				{
					return;
				}

				if (!itemInfo.IsCommand)
				{
					return;
				}

				ProcessInstantCommand(itemInfo);
			});
		}

		#endregion

		#endregion

		#region Handle Response

		#region Basket

		private void HandleBasketUnavailable(BasePlayer player)
		{
			UpdateUI(player, secondContainer =>
			{
				secondContainer.Add(new CuiLabel
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
					Text =
					{
						Text = Msg(player, MsgBasketUnavailable), Align = TextAnchor.MiddleCenter,
						Font = "robotocondensed-regular.ttf", FontSize = 20
					}
				}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Text", StoreLayer + ".BlockPanel.Text");
			});
		}

		private void HandleBasketResponse(BasePlayer player, string response, int page)
		{
#if !TESTING
			if (player == null) return;
#endif

			var firstInfo =
				JsonConvert.DeserializeObject<Dictionary<string, object>>(response,
					new KeyValuesConverter());
			if (!firstInfo.TryGetValue("result", out var value)) return;

			if (value.ToString() == "fail")
			{
				if ((firstInfo.ContainsKey("playerNotFound") && Convert.ToBoolean(firstInfo["playerNotFound"])) ||
				    (firstInfo.ContainsKey("message") &&
				     firstInfo["message"]?.ToString()?.Contains("Игрок не найден") == true))
					UpdateUI(player, secondContainer =>
					{
						secondContainer.Add(new CuiLabel
							{
								RectTransform = {AnchorMin = "0 0.1", AnchorMax = "1 1", OffsetMax = "0 0"},
								Text =
								{
									Text = Msg(player, MsgBasketNoAuth), Align = TextAnchor.MiddleCenter,
									Font = "robotocondensed-regular.ttf", FontSize = 20
								}
							}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Text",
							StoreLayer + ".BlockPanel.Text");
					});
			}
			else
			{
				if (!firstInfo.TryGetValue("data", out var dataObj) || dataObj == null)
				{
					PrintError("Unknown error #1: Data is null");
					CuiHelper.DestroyUi(player, StoreLayer);
					return;
				}

				var data = dataObj as List<object>;
				if (data == null)
				{
					PrintError("Unknown error #2: Data is not a list");
					CuiHelper.DestroyUi(player, StoreLayer);
					return;
				}

				// Логирование данных от API (только общее количество, без деталей по каждому предмету)
				LogAction(player, $"HandleBasketResponse: Received {data.Count} items from API", false);

				if (data.Count == 0)
				{
					UpdateUI(player, secondContainer =>
					{
						secondContainer.Add(new CuiLabel
							{
								RectTransform = {AnchorMin = "0 0.1", AnchorMax = "1 1", OffsetMax = "0 0"},
								Text =
								{
									Text = Msg(player, MsgBasketEmpty), Align = TextAnchor.MiddleCenter,
									Font = "robotocondensed-regular.ttf", FontSize = 34
								}
							}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.Text",
							StoreLayer + ".BlockPanel.Text");
					});
					return;
				}

				try
				{
				_playerBaskets.Remove(player.userID);

				_playerBaskets[player.userID] = new PlayerBasket(data);

				ShowBasketItemsUI(player, page);
				}
				catch (Exception ex)
				{
					PrintError($"Error processing basket data: {ex.Message}");
					CuiHelper.DestroyUi(player, StoreLayer);
				}
			}
		}

		#endregion

		#region Popular Items

		private void RequestPopularItems(BasePlayer player)
		{
			Request("store.popularItems", new Dictionary<string, string>(), (code, response) =>
			{
				if (code == 200)
				{
					HandlePopularItemsResponse(player, response);
				}
			}, player);
		}

		// Загружает популярные товары один раз при инициализации
		private void LoadPopularItems()
		{
			Request("store.popularItems", new Dictionary<string, string>(), (code, response) =>
			{
				if (code == 200)
				{
					try
					{
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
						if (json != null && json.TryGetValue("result", out var result) && result.ToString() == "success")
						{
							if (json.TryGetValue("data", out var dataObj))
							{
								var data = dataObj as List<object>;
								if (data != null)
								{
									var items = new List<Dictionary<string, object>>();
									foreach (var itemObj in data)
									{
										if (itemObj is Dictionary<string, object> item)
										{
											// Проверяем вайп-блок для каждого товара
											string productId = "";
											int itemId = 0;
											bool isBlueprint = false;

											if (item.TryGetValue("productId", out var productIdObj))
											{
												productId = productIdObj?.ToString() ?? "";
											}

											// Получаем itemId из разных возможных мест
											if (item.TryGetValue("item_id", out var itemIdObj1))
											{
												int.TryParse(itemIdObj1?.ToString(), out itemId);
											}
											else if (item.TryGetValue("itemId", out var itemIdObj2))
											{
												int.TryParse(itemIdObj2?.ToString(), out itemId);
											}
											else if (item.TryGetValue("data", out var itemDataObj))
											{
												if (itemDataObj is Dictionary<string, object> itemData)
												{
													if (itemData.TryGetValue("itemId", out var itemIdObj3))
													{
														int.TryParse(itemIdObj3?.ToString(), out itemId);
													}
												}
											}

											// Определяем, является ли товар blueprint
											if (itemId == ItemManager.blueprintBaseDef.itemid)
											{
												isBlueprint = true;
											}

											// Проверяем вайп-блок только если есть productId и itemId
											if (!string.IsNullOrEmpty(productId) && itemId != 0)
											{
												double leftTime = WB_GetLeftTime(productId, itemId, isBlueprint);
												if (leftTime > 0)
												{
													item["isBlocked"] = true;
													item["leftTime"] = leftTime;
												}
												else
												{
													item["isBlocked"] = false;
												}
											}
											else
											{
												// Для команд и товаров без itemId блокировка не применяется
												item["isBlocked"] = false;
											}

											items.Add(item);
										}
									}
									_globalPopularItems = items;
									// Популярные товары загружены (не выводим сообщение для уменьшения шума в логах)
								}
							}
						}
					}
					catch (Exception ex)
					{
						PrintError($"Error loading popular items at initialization: {ex.Message}");
					}
				}
			});
		}

		private void HandlePopularItemsResponse(BasePlayer player, string response)
		{
			try
			{
				var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
				if (json != null && json.TryGetValue("result", out var result) && result.ToString() == "success")
				{
					if (json.TryGetValue("data", out var dataObj))
					{
						var data = dataObj as List<object>;
						if (data != null)
						{
							var items = new List<Dictionary<string, object>>();
							foreach (var itemObj in data)
							{
								if (itemObj is Dictionary<string, object> item)
								{
									// Проверяем вайп-блок для каждого товара
									string productId = "";
									int itemId = 0;
									bool isBlueprint = false;

									if (item.TryGetValue("productId", out var productIdObj))
									{
										productId = productIdObj?.ToString() ?? "";
									}

									// Получаем itemId из разных возможных мест
									if (item.TryGetValue("item_id", out var itemIdObj1))
									{
										int.TryParse(itemIdObj1?.ToString(), out itemId);
									}
									else if (item.TryGetValue("itemId", out var itemIdObj2))
									{
										int.TryParse(itemIdObj2?.ToString(), out itemId);
									}
									else if (item.TryGetValue("data", out var itemDataObj))
									{
										if (itemDataObj is Dictionary<string, object> itemData)
										{
											if (itemData.TryGetValue("itemId", out var itemIdObj3))
											{
												int.TryParse(itemIdObj3?.ToString(), out itemId);
											}
										}
									}

									// Определяем, является ли товар blueprint
									// Blueprint имеет itemid = ItemManager.blueprintBaseDef.itemid (который равен -1580979675)
									if (itemId == ItemManager.blueprintBaseDef.itemid)
									{
										isBlueprint = true;
									}

									// Проверяем вайп-блок только если есть productId и itemId
									if (!string.IsNullOrEmpty(productId) && itemId != 0)
									{
										double leftTime = WB_GetLeftTime(productId, itemId, isBlueprint);
										if (leftTime > 0)
										{
											item["isBlocked"] = true;
											item["leftTime"] = leftTime;
										}
										else
										{
											item["isBlocked"] = false;
										}
									}
									else
									{
										// Для команд и товаров без itemId блокировка не применяется
										item["isBlocked"] = false;
									}

									items.Add(item);
								}
							}
							_popularItems[player.userID] = items;
							ShowPopularItemsUI(player);
						}
					}
				}
			}
			catch (Exception ex)
			{
				PrintError($"Error parsing popular items: {ex.Message}");
			}
		}

		private void ShowPopularItemsUI(BasePlayer player)
		{
			// Используем глобальный список популярных товаров, если он загружен
			List<Dictionary<string, object>> items = null;
			if (_globalPopularItems != null && _globalPopularItems.Count > 0)
			{
				items = _globalPopularItems;
			}
			else if (_popularItems.TryGetValue(player.userID, out var playerItems) && playerItems != null && playerItems.Count > 0)
			{
				// Fallback на персональный список (для обратной совместимости)
				items = playerItems;
			}

			if (items == null || items.Count == 0)
				return;

			// Удаляем старые карточки популярных товаров
			for (int i = 0; i < 8; i++)
			{
				CuiHelper.DestroyUi(player, StoreLayer + $".PopularItemsPanel.Item{i}");
			}

			var container = new CuiElementContainer();

			// Отображаем до 8 товаров в 2 колонки (4 ряда)
			// Используем тот же размер, что и для карточек корзины (ItemSide x ItemSide)
			int maxItems = Math.Min(items.Count, 8);
			int itemsPerRow = 2; // 2 колонки

			// Используем тот же метод расчета позиций, что и для корзины (UI_RecountPosition)
			// Но адаптируем для 2 колонок вместо 7
			for (int i = 0; i < maxItems; i++)
			{
				var item = items[i];

				// Вычисляем позицию используя UI_RecountPosition для 2 колонок
				UI_RecountPositionPopular(out var xSwitch, out var ySwitch, i, maxItems, itemsPerRow);

				ShowPopularItemUI(player, item, ref container, i, (float)xSwitch, (float)ySwitch);
			}

			CuiHelper.AddUi(player, container);
		}

		private void ShowPopularItemUI(BasePlayer player, Dictionary<string, object> itemData, ref CuiElementContainer container,
			int index, float xPos, float yPos)
		{
			// Получаем данные товара
			if (!itemData.TryGetValue("productId", out var productIdObj))
			{
				PrintError($"GameStoresRUST: ShowPopularItemUI: productId not found in itemData at index {index}");
				return;
			}
			if (!int.TryParse(productIdObj?.ToString(), out var dropId))
			{
				PrintError($"GameStoresRUST: ShowPopularItemUI: Failed to parse productId '{productIdObj}' as int at index {index}");
				return;
			}

			// Логирование для отладки - какой dropId используется при создании карточки
			string itemName = itemData.TryGetValue("name", out var nameObjDebug) ? nameObjDebug?.ToString() ?? "" : "";
			int itemIdFromData = 0;
			if (itemData.TryGetValue("itemId", out var itemIdObj))
				int.TryParse(itemIdObj?.ToString(), out itemIdFromData);
			else if (itemData.TryGetValue("item_id", out var itemIdObj2))
				int.TryParse(itemIdObj2?.ToString(), out itemIdFromData);

			PrintError($"ShowPopularItemUI: Creating card. index: {index}, dropId (productId): {dropId}, itemName: {itemName}, itemId: {itemIdFromData}, productId from data: {productIdObj?.ToString()}");

			// Отладочное логирование отключено для уменьшения шума в логах

			string name = itemData.TryGetValue("name", out var nameObj) ? nameObj?.ToString() ?? "Товар" : "Товар";
			string imgUrl = itemData.TryGetValue("img", out var imgObj) ? imgObj?.ToString() ?? "" : "";
			// Получаем цену из API (уже возвращается в formatPopularItem)
			int price = 0;
			if (itemData.TryGetValue("price", out var priceObj))
			{
				if (priceObj != null)
				{
					if (!int.TryParse(priceObj.ToString(), out price))
					{
						// Пробуем как double/float
						if (double.TryParse(priceObj.ToString(), out var priceDouble))
						{
							price = (int)Math.Ceiling(priceDouble);
						}
					}
				}
			}

			// Контейнер для карточки - позиционируем от правого края панели PopularItemsPanel
			// Используем ту же точку привязки по вертикали (0.55), что и для корзины, чтобы товары были на одной высоте
			container.Add(new CuiElement
			{
				Parent = StoreLayer + ".PopularItemsPanel", // Родитель - панель популярных товаров (правая часть)
				Name = StoreLayer + $".PopularItemsPanel.Item{index}",
				Components =
				{
					new CuiRectTransformComponent
					{
						AnchorMin = "1 0.55", // Привязываем к правому краю и 55% высоты панели (как у корзины)
						AnchorMax = "1 0.55", // Привязываем к правому краю и 55% высоты панели
						OffsetMin = $"{xPos} {yPos - _config.UI.ItemSide}", // Левый нижний угол в пикселях (от точки привязки, xPos отрицательный)
						OffsetMax = $"{xPos + _config.UI.ItemSide} {yPos}" // Правый верхний угол в пикселях (от точки привязки)
					}
				}
			});

			// Проверяем, заблокирован ли товар вайп-блоком
			bool isBlocked = false;
			double leftTime = 0;
			if (itemData.TryGetValue("isBlocked", out var isBlockedObj))
			{
				isBlocked = isBlockedObj?.ToString()?.ToLower() == "true" || isBlockedObj?.ToString() == "1";
			}
			if (itemData.TryGetValue("leftTime", out var leftTimeObj))
			{
				double.TryParse(leftTimeObj?.ToString(), out leftTime);
			}

			// Определяем, какое изображение фона использовать в зависимости от статуса
			string cardImageName = ImageCardPopular; // Используем card_popular для популярных товаров
			string cardImageUrl = "https://storage.prostoj.store/plugin-file/card_popular.png";

			// Если товар заблокирован вайп-блоком, используем красную карточку
			if (isBlocked)
			{
				cardImageName = ImageCardRed;
				cardImageUrl = "https://storage.prostoj.store/plugin-file/card_red.png";
			}

			// Используем кешированное изображение, если доступно
			string cachedCardId = GetCachedImageId(cardImageName);
			if (!string.IsNullOrEmpty(cachedCardId))
			{
				cardImageUrl = null; // Указываем, что используем кеш
			}

			// Получаем кешированное изображение light.png (как в корзине)
			string cachedLightId = GetCachedImageId(ImageLight);
			if (string.IsNullOrEmpty(cachedLightId) && _imageCache != null)
			{
				cachedLightId = _imageCache.GetImageId("light");
			}

			// Изображение light.png на всю карточку как прозрачный фон (добавляем ПЕРВЫМ, под card изображениями)
			container.Add(new CuiElement
			{
				Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
				Name = StoreLayer + $".PopularItemsPanel.Item{index}.LightBg",
				Components =
				{
					!string.IsNullOrEmpty(cachedLightId)
						? new CuiRawImageComponent {Png = cachedLightId, Color = "1 1 1 1"}
						: new CuiRawImageComponent {Url = "https://storage.prostoj.store/plugin-file/light.png", Color = "1 1 1 1"},
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 1",
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					}
				}
			});

			// Фон карточки (добавляем ВТОРЫМ, поверх light.png)
			container.Add(new CuiElement
			{
				Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
				Name = StoreLayer + $".PopularItemsPanel.Item{index}.CardBg",
				Components =
				{
					cardImageUrl == null
						? GetCachedImageComponent(cardImageName, cardImageUrl ?? "https://storage.prostoj.store/plugin-file/card_popular.png")
						: GetCardImageComponent(cardImageName, cardImageUrl),
					new CuiRectTransformComponent
					{
						AnchorMin = "0 0",
						AnchorMax = "1 1",
						OffsetMin = "0 0",
						OffsetMax = "0 0"
					}
				}
			});

			// Получаем item_id и type для определения, является ли это предметом или чертежом
			int itemId = 0;
			bool isBlueprint = false;
			if (itemData.TryGetValue("item_id", out var itemIdObj1))
			{
				int.TryParse(itemIdObj1?.ToString(), out itemId);
			}
			else if (itemData.TryGetValue("itemId", out var itemIdObj2))
			{
				int.TryParse(itemIdObj2?.ToString(), out itemId);
			}

			// Проверяем, является ли это чертежом
			if (itemId == ItemManager.blueprintBaseDef.itemid)
			{
				isBlueprint = true;
			}
			else if (itemData.TryGetValue("type", out var typeObj))
			{
				string type = typeObj?.ToString() ?? "";
				if (type.ToLower() == "blueprint")
				{
					isBlueprint = true;
				}
			}

			// Если есть item_id, всегда используем CuiImageComponent (как в корзине)
			// Это позволяет использовать встроенные иконки игры вместо загрузки по URL
			if (itemId != 0)
			{
				ItemDefinition itemDefinition = ItemManager.FindItemDefinition(itemId);
				if (itemDefinition != null)
				{
					// Если это чертеж, используем специальную иконку чертежа
					if (isBlueprint)
					{
						container.Add(new CuiElement
						{
							Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
							Components =
							{
								new CuiImageComponent {ItemId = ItemManager.blueprintBaseDef.itemid},
								new CuiRectTransformComponent
									{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
							}
						});
					}
					else
					{
						// Обычный предмет
						container.Add(new CuiElement
						{
							Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
							Components =
							{
								new CuiImageComponent {ItemId = itemDefinition.itemid},
								new CuiRectTransformComponent
									{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
							}
						});
					}
				}
			}
			// Если нет item_id или LoadSpriteImages отключен, используем URL изображения (как в корзине)
			// НО только если imgUrl является валидным URL (начинается с http:// или https://)
			// Если imgUrl является числом (item ID), не пытаемся загружать его как URL
			else if (!string.IsNullOrEmpty(imgUrl) && (imgUrl.StartsWith("http://", StringComparison.OrdinalIgnoreCase) || imgUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase)))
			{
				string imageUrlToUse = imgUrl;
				string fileName = $"IconGS.Popular.{dropId}";

				// Проверяем кеш изображений предметов (загружается при использовании, не при инициализации)
				if (_imageCache != null)
				{
					// Проверяем, есть ли уже кешированное изображение
					string cachedItemImageId = _imageCache.GetItemImageId(fileName);
					if (!string.IsNullOrEmpty(cachedItemImageId))
					{
						// Используем кешированное изображение
						imageUrlToUse = cachedItemImageId;
					}
					else
					{
						// Если кеша нет, начинаем загрузку в фоне (для следующих разов)
						// Сейчас используем URL напрямую
						_imageCache.CacheItemImage(fileName, imgUrl);
					}
				}

				// Отображаем изображение товара с теми же отступами что и в корзине
				if (!imageUrlToUse.Contains("http"))
				{
					container.Add(new CuiElement
					{
						Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
						Components =
						{
							new CuiRawImageComponent {Png = imageUrlToUse},
							new CuiRectTransformComponent
								{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
						}
					});
				}
				else
				{
					container.Add(new CuiElement
					{
						Parent = StoreLayer + $".PopularItemsPanel.Item{index}",
						Components =
						{
							new CuiRawImageComponent {Url = imageUrlToUse},
							new CuiRectTransformComponent
								{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "18 18", OffsetMax = "-18 -18"}
						}
					});
				}
			}

			// Цена в правом верхнем углу (белый цвет с прозрачностью 30%)
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = "-80 -25", OffsetMax = "-5 -5"},
				Text = {Text = $"{price} ₽", Align = TextAnchor.UpperRight, Font = "robotocondensed-bold.ttf", FontSize = 14, Color = "1 1 1 0.3"}
			}, StoreLayer + $".PopularItemsPanel.Item{index}", StoreLayer + $".PopularItemsPanel.Item{index}.Price");

			// Название товара внизу карточки
			container.Add(new CuiLabel
			{
				RectTransform = {AnchorMin = "0 0", AnchorMax = "1 0.25", OffsetMin = "5 5", OffsetMax = "-5 -5"},
				Text = {Text = name, Align = TextAnchor.LowerLeft, Font = "robotocondensed-regular.ttf", FontSize = 11, Color = "0.9 0.9 0.9 1"}
			}, StoreLayer + $".PopularItemsPanel.Item{index}", StoreLayer + $".PopularItemsPanel.Item{index}.Name");

			// Если товар заблокирован вайп-блоком, показываем время до разблокировки поверх карточки
			if (isBlocked && leftTime > 0)
			{
				// Товар заблокирован вайп-блоком - показываем время до разблокировки
				string timeText = FormatTime(player, leftTime);
				container.Add(new CuiButton
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
					Button = {FadeIn = 1f, Color = "0 0 0 0", Command = $"UI_GameStoresRUST buypopular {dropId}"}, // Кнопка не блокируется
					Text = {Text = Msg(player, MsgTakeGiveBlocked, timeText), Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf", Color = "1.00 0.09 0.00 1.00", FontSize = 16}
				}, StoreLayer + $".PopularItemsPanel.Item{index}", StoreLayer + $".PopularItemsPanel.Item{index}.BlockedOverlay");
			}
			else
			{
				// Обычная кнопка покупки
				container.Add(new CuiButton
				{
					RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
					Button = {Color = "0 0 0 0", Command = $"UI_GameStoresRUST buypopular {dropId}"},
					Text = {Text = string.Empty}
				}, StoreLayer + $".PopularItemsPanel.Item{index}", StoreLayer + $".PopularItemsPanel.Item{index}.BuyButton");
			}
		}

		private void ProcessBuyPopularItem(BasePlayer player, int dropId)
		{

			if (maintenance)
			{
				Reply(player, Maintenance);
				return;
			}

			if (!Initialized)
			{
				HandleInitializationError(player);
				return;
			}

			// Проверки блокировок
			if (_config.Plugin.UseBuildingBlocked && player.IsBuildingBlocked())
			{
				ShowNotify(player, Msg(player, MsgBuildingBlocked), true);
				return;
			}

			if (_config.Plugin.UseCombatBlocked && NE_IsCombatBlocked(player))
			{
				ShowNotify(player, Msg(player, MsgCombatBlocked), true);
				return;
			}

			if (_config.Plugin.UseRaidBlocked && NE_IsRaidBlocked(player))
			{
				ShowNotify(player, Msg(player, MsgRaidBlocked), true);
				return;
			}

			// Проверяем вайп-блок для популярного товара
			// Используем _globalPopularItems, так как именно он используется для отображения товаров
			List<Dictionary<string, object>> items = null;
			if (_globalPopularItems != null && _globalPopularItems.Count > 0)
			{
				items = _globalPopularItems;
			}
			else if (_popularItems.TryGetValue(player.userID, out var playerItems) && playerItems != null)
			{
				items = playerItems;
			}

			if (items != null)
			{
				var item = items.FirstOrDefault(i =>
					i.TryGetValue("productId", out var pid) &&
					int.TryParse(pid?.ToString(), out var id) &&
					id == dropId);

				if (item != null)
				{
					bool isBlocked = false;
					double leftTime = 0;

					if (item.TryGetValue("isBlocked", out var isBlockedObj))
					{
						isBlocked = isBlockedObj?.ToString()?.ToLower() == "true" || isBlockedObj?.ToString() == "1";
					}

					if (item.TryGetValue("leftTime", out var leftTimeObj))
					{
						double.TryParse(leftTimeObj?.ToString(), out leftTime);
					}

					// Если товар заблокирован, проверяем еще раз (на случай, если время изменилось)
					if (isBlocked && leftTime > 0)
					{
						// Получаем данные для проверки вайп-блока
						string productId = "";
						int itemId = 0;
						bool isBlueprint = false;

						if (item.TryGetValue("productId", out var productIdObj))
						{
							productId = productIdObj?.ToString() ?? "";
						}

						// Получаем itemId из разных возможных мест
						if (item.TryGetValue("item_id", out var itemIdObj1))
						{
							int.TryParse(itemIdObj1?.ToString(), out itemId);
						}
						else if (item.TryGetValue("itemId", out var itemIdObj2))
						{
							int.TryParse(itemIdObj2?.ToString(), out itemId);
						}
						else if (item.TryGetValue("data", out var itemDataObj2))
						{
							if (itemDataObj2 is Dictionary<string, object> itemData2)
							{
								if (itemData2.TryGetValue("itemId", out var itemIdObj3))
								{
									int.TryParse(itemIdObj3?.ToString(), out itemId);
								}
							}
						}

						// Определяем, является ли товар blueprint
						if (itemId == ItemManager.blueprintBaseDef.itemid)
						{
							isBlueprint = true;
						}

						if (!string.IsNullOrEmpty(productId) && itemId != 0)
						{
							double currentLeftTime = WB_GetLeftTime(productId, itemId, isBlueprint);
							if (currentLeftTime > 0)
							{
								string time = FormatTime(player, currentLeftTime);
								ShowNotify(player, Msg(player, MsgTakeItemBlocked, time), true);
								return;
							}
						}
						else
						{
							// Если нет данных для проверки, используем сохраненное время
							string time = FormatTime(player, leftTime);
							ShowNotify(player, Msg(player, MsgTakeItemBlocked, time), true);
							return;
						}
					}
				}
			}

			// Логирование для отладки - какой dropId передается в API
			var itemForLogging = items?.FirstOrDefault(i =>
				i.TryGetValue("productId", out var pid) &&
				int.TryParse(pid?.ToString(), out var id) &&
				id == dropId);

			if (itemForLogging != null)
			{
				string itemName = "";
				int itemIdFromList = 0;
				if (itemForLogging.TryGetValue("name", out var nameObj))
					itemName = nameObj?.ToString() ?? "";
				if (itemForLogging.TryGetValue("itemId", out var itemIdObj))
					int.TryParse(itemIdObj?.ToString(), out itemIdFromList);
				else if (itemForLogging.TryGetValue("item_id", out var itemIdObj2))
					int.TryParse(itemIdObj2?.ToString(), out itemIdFromList);

				PrintError($"ProcessBuyPopularItem: Purchasing item. dropId: {dropId}, itemName: {itemName}, itemId from list: {itemIdFromList}");
			}
			else
			{
				PrintError($"ProcessBuyPopularItem: Item not found in list for dropId: {dropId}");
			}

			// Отправляем запрос на покупку
			Request("store.buyAndTake", new Dictionary<string, string>
			{
				{"dropId", dropId.ToString()},
				{"quantity", "1"}
			}, (code, response) =>
			{
				HandleBuyPopularItemResponse(player, dropId, code, response);
			}, player);
		}

		private void HandleBuyPopularItemResponse(BasePlayer player, int dropId, int code, string response)
		{
			switch (code)
			{
				case 200:
					try
					{
						var json = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
						if (json != null && json.TryGetValue("result", out var result) && result.ToString() == "success")
						{
							if (json.TryGetValue("data", out var dataObj))
							{
								var data = dataObj as Dictionary<string, object>;
								if (data != null)
								{
									// Получаем информацию о выданных товарах
									if (data.TryGetValue("items", out var itemsObj))
									{
										var items = itemsObj as List<object>;
										if (items != null && items.Count > 0)
										{
											// Выдаем товары игроку сразу после покупки
											int itemsProcessed = 0;
											List<int> basketIds = new List<int>(); // Собираем basketId для пометки как выданные

											foreach (var itemObj in items)
											{
												if (itemObj is Dictionary<string, object> item)
												{
													try
													{
														// Получаем basketId из ответа API для пометки как выданный
														if (item.TryGetValue("basketId", out var basketIdObj))
														{
															if (int.TryParse(basketIdObj?.ToString(), out var basketId))
															{
																basketIds.Add(basketId);
															}
														}
														else if (item.TryGetValue("id", out var idObj))
														{
															// Альтернативный ключ для basketId
															if (int.TryParse(idObj?.ToString(), out var basketId))
															{
																basketIds.Add(basketId);
															}
														}

														// Проверяем, есть ли вложенная структура "data" (как в корзине)
														Dictionary<string, object> itemData = item;
														if (item.TryGetValue("data", out var itemDataObj) && itemDataObj is Dictionary<string, object> nestedData)
														{
															// Если есть вложенная структура, используем её, но сохраняем basketId и другие поля из корня
															itemData = new Dictionary<string, object>(nestedData);
															// Копируем важные поля из корня, если их нет во вложенных данных
															if (!itemData.ContainsKey("basketId") && item.TryGetValue("basketId", out var rootBasketId))
																itemData["basketId"] = rootBasketId;
															if (!itemData.ContainsKey("productId") && item.TryGetValue("productId", out var rootProductId))
																itemData["productId"] = rootProductId;
															if (!itemData.ContainsKey("name") && item.TryGetValue("name", out var rootName))
																itemData["name"] = rootName;
															if (!itemData.ContainsKey("type") && item.TryGetValue("type", out var rootType))
																itemData["type"] = rootType;
															if (!itemData.ContainsKey("amount") && item.TryGetValue("amount", out var rootAmount))
																itemData["amount"] = rootAmount;
															if (!itemData.ContainsKey("img") && item.TryGetValue("img", out var rootImg))
																itemData["img"] = rootImg;
															// ВСЕГДА копируем itemId и item_id из корня (даже если они есть во вложенных данных)
															// Это гарантирует, что используется правильный itemId из API ответа
															if (item.TryGetValue("itemId", out var rootItemId))
																itemData["itemId"] = rootItemId;
															if (item.TryGetValue("item_id", out var rootItemId2))
																itemData["item_id"] = rootItemId2;
														}
														else
														{
															// Если вложенной структуры нет, используем корневые данные напрямую
															// Но убеждаемся, что itemId и item_id доступны
															itemData = new Dictionary<string, object>(item);
														}

														// Проверяем, является ли это набором (set) с вложенными items
														// Сначала проверяем тип товара - если это "set", ищем поле "items"
														bool isSet = false;
														bool itemProcessed = false;
														List<object> setItems = null;

														// Проверяем тип товара
														string itemType = null;
														if (itemData.TryGetValue("type", out var typeObj))
														{
															itemType = typeObj?.ToString();
														}

														// Если тип "set" или есть поле "items", это набор
														if (itemType == "set" || itemData.ContainsKey("items") ||
														    (itemData.TryGetValue("data", out var checkDataObj) && checkDataObj is Dictionary<string, object> checkData && checkData.ContainsKey("items")))
														{
															// Проверяем в корне данных
															if (itemData.TryGetValue("items", out var setItemsObj))
															{
																if (setItemsObj is List<object> itemsList && itemsList.Count > 0)
																{
																	setItems = itemsList;
																	isSet = true;
																}
															}
															// Проверяем во вложенной структуре data
															else if (itemData.TryGetValue("data", out var setDataObj) && setDataObj is Dictionary<string, object> setNestedData)
															{
																if (setNestedData.TryGetValue("items", out var nestedSetItemsObj) && nestedSetItemsObj is List<object> nestedItemsList && nestedItemsList.Count > 0)
																{
																	setItems = nestedItemsList;
																	isSet = true;
																}
															}

															// Если тип "set", но items не найдены, выводим предупреждение
															if (itemType == "set" && !isSet)
															{
																PrintError($"Popular item has type 'set' but no 'items' field found. Item data: {JsonConvert.SerializeObject(itemData)}");
															}
														}

														if (isSet && setItems != null && setItems.Count > 0)
														{
															// Обрабатываем набор - выдаем каждый элемент отдельно
															foreach (var setItemObj in setItems)
															{
																if (setItemObj is Dictionary<string, object> setItem)
																{
																	try
																	{
																		// Копируем basketId из корневого элемента в каждый элемент набора
																		if (!setItem.ContainsKey("basketId") && itemData.TryGetValue("basketId", out var rootBasketId))
																			setItem["basketId"] = rootBasketId;

																		// Создаем WItem для каждого элемента набора
																		var setWItem = new WItem(setItem);
																		if (setWItem.IsValid)
																		{
																			if (setWItem.IsItem)
																			{
																				ProcessItem(player, setWItem);
																				itemsProcessed++;
																			}
																			else if (setWItem.IsCommand)
																			{
																				ProcessCommand(player, setWItem);
																				itemsProcessed++;
																			}
																			else if (setWItem.IsBlueprint)
																			{
																				ProcessBlueprint(player, setWItem);
																				itemsProcessed++;
																			}

																			// Собираем basketId для пометки как выданный
																			if (!string.IsNullOrEmpty(setWItem.basketId) && int.TryParse(setWItem.basketId, out var setBasketId))
																			{
																				if (!basketIds.Contains(setBasketId))
																					basketIds.Add(setBasketId);
																			}
																		}
																	}
																	catch (Exception e)
																	{
																		PrintError($"Error processing set item: {e.Message}\nStack trace: {e.StackTrace}");
																		ShowNotify(player, $"Ошибка при выдаче элемента набора: {e.Message}", true);
																	}
																}
															}
															itemProcessed = true; // Набор обработан
														}
														else
														{
														// Обычный товар (не набор)
														// Логирование для отладки
														PrintError($"Creating WItem from itemData. itemId in itemData: {itemData.ContainsKey("itemId")}, item_id in itemData: {itemData.ContainsKey("item_id")}");
														if (itemData.ContainsKey("itemId"))
														{
															PrintError($"itemId value in itemData: {itemData["itemId"]} (type: {itemData["itemId"]?.GetType()?.Name ?? "null"})");
														}
														if (itemData.ContainsKey("item_id"))
														{
															PrintError($"item_id value in itemData: {itemData["item_id"]} (type: {itemData["item_id"]?.GetType()?.Name ?? "null"})");
														}
														// Также проверяем в корне item
														if (item.ContainsKey("itemId"))
														{
															PrintError($"itemId value in root item: {item["itemId"]} (type: {item["itemId"]?.GetType()?.Name ?? "null"})");
														}
														if (item.ContainsKey("item_id"))
														{
															PrintError($"item_id value in root item: {item["item_id"]} (type: {item["item_id"]?.GetType()?.Name ?? "null"})");
														}

														var wItem = new WItem(itemData);

														PrintError($"WItem created. ItemID: {wItem.ItemID}, IsItem: {wItem.IsItem}, itemDefinition: {wItem.itemDefinition?.shortname ?? "null"}");

														if (wItem.IsValid)
														{
															if (wItem.IsItem)
															{
																try
																{
																	ProcessItem(player, wItem);
																	itemsProcessed++;
																	itemProcessed = true;
																}
																catch (Exception e)
																{
																	PrintError($"Error in ProcessItem for popular item: {e.Message}\nStack trace: {e.StackTrace}");
																	ShowNotify(player, $"Ошибка при выдаче предмета: {e.Message}", true);
																}
															}
																else if (wItem.IsCommand)
																{
																	try
																	{
																		ProcessCommand(player, wItem);
																		if (!itemProcessed) itemsProcessed++;
																		itemProcessed = true;
																	}
																	catch (Exception e)
																	{
																		PrintError($"Error in ProcessCommand for popular item: {e.Message}\nStack trace: {e.StackTrace}");
																		ShowNotify(player, $"Ошибка при выполнении команды: {e.Message}", true);
																	}
																}
																else if (wItem.IsBlueprint)
																{
																	try
																	{
																		ProcessBlueprint(player, wItem);
																		if (!itemProcessed) itemsProcessed++;
																		itemProcessed = true;
																	}
																	catch (Exception e)
																	{
																		PrintError($"Error in ProcessBlueprint for popular item: {e.Message}\nStack trace: {e.StackTrace}");
																		ShowNotify(player, $"Ошибка при выдаче чертежа: {e.Message}", true);
																	}
																}
																else
																{
																	// Если товар валиден, но не имеет типа, возможно это набор без поля "items" или товар с неправильной структурой
																	// Выводим подробную информацию для отладки
																	PrintError($"Popular item is valid but no type matched: IsItem={wItem.IsItem}, IsCommand={wItem.IsCommand}, IsBlueprint={wItem.IsBlueprint}, IsSet={wItem.IsSet}");
																	PrintError($"Item data: {JsonConvert.SerializeObject(itemData)}");
																	PrintError($"WItem details: ItemID={wItem.ItemID}, itemDefinition={wItem.itemDefinition?.shortname ?? "null"}, ImageUrl={wItem.ImageUrl ?? "null"}");
																	// Проверяем, есть ли itemId в данных, но не парсится
																	if (itemData.ContainsKey("itemId") || itemData.ContainsKey("item_id"))
																	{
																		var debugItemId = itemData.ContainsKey("itemId") ? itemData["itemId"] : itemData["item_id"];
																		PrintError($"itemId in data: {debugItemId} (type: {debugItemId?.GetType()?.Name ?? "null"}), but ItemID={wItem.ItemID}");
																	}
																	ShowNotify(player, "Ошибка: товар не может быть обработан. Товар добавлен в корзину.", true);
																}
															}
															else
															{
																// Товар невалиден - выводим информацию для отладки
																PrintError($"Popular item is not valid: {JsonConvert.SerializeObject(itemData)}");
																ShowNotify(player, "Ошибка: товар не может быть обработан. Товар добавлен в корзину.", true);
															}
														}
													}
													catch (Exception e)
													{
														PrintError($"Error processing popular item: {e.Message}\nStack trace: {e.StackTrace}\nItem data: {JsonConvert.SerializeObject(itemObj)}");
														// Показываем уведомление об ошибке обработки товара
														ShowNotify(player, $"Ошибка при выдаче товара: {e.Message}", true);
														HandleItemProcessingError(player, null, e);
													}
												}
											}

											// Помечаем все выданные товары как выданные через baskets.makeIssued (как в корзине)
											// Примечание: API store.buyAndTake может уже пометить товары как выданные,
											// поэтому ошибка 107 (предмет уже получен/продан) не является критической
											foreach (var basketId in basketIds)
											{
												Request("baskets.makeIssued", new Dictionary<string, string>
												{
													{"steamId", player.UserIDString},
													{"basketId", basketId.ToString()}
												}, (makeIssuedCode, makeIssuedResponse) =>
												{
													if (makeIssuedCode != 200)
													{
														// Проверяем, не является ли это ошибкой "предмет уже получен/продан" (код 107)
														try
														{
															var responseObj = JsonConvert.DeserializeObject<Dictionary<string, object>>(makeIssuedResponse);
															if (responseObj != null && responseObj.TryGetValue("code", out var codeObj))
															{
																var code = codeObj?.ToString();
																// Код 107 означает, что предмет уже помечен как выданный - это нормально
																if (code == "107")
																{
																	// Это не ошибка, товар уже помечен как выданный (API store.buyAndTake уже пометил его)
																	return;
																}
															}
														}
														catch
														{
															// Если не удалось распарсить ответ, продолжаем как обычно
														}

														PrintError($"Failed to mark popular item as issued (basketId: {basketId}): {makeIssuedResponse}");
													}
												}, player);
											}

											if (itemsProcessed == 0)
											{
												PrintError($"No items were processed from buyAndTake response. Items count: {items.Count}");
												// Показываем уведомление об ошибке выдачи товара
												ShowNotify(player, "Товар куплен, но произошла ошибка при выдаче. Товар добавлен в корзину.", true);
											}
											else
											{
												// Показываем уведомление об успешной покупке и выдаче через Notify API
												if (data.TryGetValue("newBalance", out var balanceObj))
												{
													// Форматируем баланс в денежном формате
													string formattedBalance = FormatBalance(balanceObj);
													ShowNotify(player, $"Товар успешно куплен и выдан! Баланс: {formattedBalance} ₽");
												}
												else
												{
													ShowNotify(player, "Товар успешно куплен и выдан!");
												}
											}
										}
										else
										{
											PrintError("Items list is empty in buyAndTake response");
											ShowNotify(player, "Товар куплен, но произошла ошибка при выдаче. Товар добавлен в корзину.", true);
										}
									}
									else
									{
										PrintError("No 'items' field in buyAndTake response data");
										ShowNotify(player, "Товар куплен, но произошла ошибка при выдаче. Товар добавлен в корзину.", true);
									}

									// Обновляем список популярных товаров
									RequestPopularItems(player);
								}
							}
						}
						else
						{
							// Обрабатываем ошибки из API
							string errorMessage = "Ошибка при покупке товара";
							int errorCode = 0;

							if (json != null)
							{
								if (json.TryGetValue("message", out var message))
								{
									errorMessage = message.ToString();
								}

								if (json.TryGetValue("code", out var codeObj))
								{
									if (int.TryParse(codeObj.ToString(), out errorCode))
									{
										// Определяем более понятное сообщение по коду ошибки
										switch (errorCode)
										{
											case 105:
												if (errorMessage.Contains("dropId"))
													errorMessage = "Ошибка: не указан товар";
												else if (errorMessage.Contains("Количество"))
													errorMessage = "Ошибка: неверное количество товара";
												break;
											case 107:
												errorMessage = "Товар не найден или недоступен для покупки";
												break;
											case 108:
												errorMessage = "Недостаточно средств на счете";
												break;
											case 500:
												if (errorMessage.Contains("создания записи"))
													errorMessage = "Ошибка создания записи товара";
												else if (errorMessage.Contains("покупке"))
													errorMessage = errorMessage; // Используем сообщение из API
												break;
										}
									}
								}
							}

							// Показываем уведомление об ошибке через Notify API
							ShowNotify(player, errorMessage, true);
						}
					}
					catch (Exception ex)
					{
						PrintError($"Error processing buy response: {ex.Message}");
						ShowNotify(player, "Ошибка при покупке товара", true);
					}
					break;
				case 0:
					ShowNotify(player, "Сервер не ответил на запрос. Попробуйте позже.", true);
					break;
				default:
					ShowNotify(player, $"Ошибка при покупке товара (код: {code})", true);
					break;
			}
		}

		#endregion

		#region Item

		private void HandleItemGiveError(BasePlayer player, Item item, WItem itemInfo)
		{
			if (item == null)
			{
				PrintError($"GameStoresRUST: HandleItemGiveError called with null item for {itemInfo?.Name ?? "unknown"}");
				ShowNotify(player, "Ошибка: не удалось выдать предмет. Товар добавлен в корзину.", true);
				return;
			}

			item.Drop(player.GetDropPosition(), player.GetDropVelocity());
			// Показываем уведомление о том, что предмет выпал на землю (инвентарь полон)
			ShowNotify(player, "Инвентарь полон! Предмет выпал на землю.", true);
		}

		private void HandleItemProcessingError(BasePlayer player, WItem itemInfo, Exception e)
		{
			LogToFile("!Errors", $"{DateTime.Now.ToShortTimeString()}| Error: '{e}'", this);
			PrintError($"Error receiving product [{itemInfo?.basketId ?? "unknown"}], info saved to log");
			// Показываем уведомление об ошибке обработки предмета
			if (player != null)
			{
				ShowNotify(player, Msg(player, MsgTakeGiveError), true);
			}
		}

		#endregion

		#region Commands

		private void HandleInitializationError(BasePlayer player)
		{
			ShowNotify(player, Msg(player, MsgPluginnotinitialized), true);
		}

		#endregion

		private void HandleFetchShopUrlResponse(int code, string response)
		{
			try
			{
				if (response.Length < 1)
				{
					LogToFile("!Errors", $"{DateTime.Now.ToShortTimeString()}| Response: '{response}'", this);
					SetReserveApiLink();

					initialization = false;
					return;
				}

				var infoResult = JsonConvert.DeserializeObject<InfoResult>(response);
				if (infoResult?.data == null)
				{
					initialization = false;
					return;
				}

				ShopURL = infoResult.data.link;
				StartBalance = infoResult.data.defaultBalance;

				foreach (var pl in BasePlayer.activePlayerList) OnPlayerConnected(pl);

				Initialized = true;
				initialization = false;
			}
			catch (JsonException e)
			{
				LogToFile("Errors", $"{DateTime.Now.ToShortTimeString()}| JsonError | Response: '{response}'", this);

				SetReserveApiLink();

				initialization = false;
			}
		}

		#endregion

		#region Bans

		private bool HandleCommandBan(BasePlayer player, ConsoleSystem.Arg args)
		{
			if (ListBannedCommandUserID.Contains(player.userID))
			{
				var msg = Msg(player, MsgPlayerFloodBlock);

				player.ChatMessage(msg);

				ShowNotify(player, msg);

				if (args.GetString(0) == "take")
				{
					var index = args.GetInt(1);
					var basketID = args.GetInt(2);

					// Заменяем фон карточки на card_red.png при ошибке
					UpdateUI(player, container =>
					{
						// Удаляем старое изображение фона
						CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

						// Добавляем новое изображение card_red.png
						container.Add(new CuiElement
						{
							Parent = StoreLayer + $".BlockPanel.{index}",
							Name = StoreLayer + $".BlockPanel.{index}.CardBg",
							Components =
							{
								GetCardImageComponent(ImageCardRed, "https://storage.prostoj.store/plugin-file/card_red.png"),
								new CuiRectTransformComponent
								{
									AnchorMin = "0 0",
									AnchorMax = "1 1",
									OffsetMin = "0 0",
									OffsetMax = "0 0"
								}
							}
						});

						// Overlay без цветного фона, только текст
						container.Add(new CuiButton
							{
								RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
								Button =
								{
								FadeIn = 1f, Color = "0 0 0 0", // Прозрачный фон вместо красного
									Close = StoreLayer + ".BlockPanel." + index + ".Open",
									Command = $"UI_GameStoresRUST take {index} {basketID}"
								},
								Text =
								{
									Text = Msg(player, MsgTakeGiveError), Align = TextAnchor.MiddleCenter,
								Font = "robotocondensed-bold.ttf", Color = "1.00 0.09 0.00 1.00", FontSize = 16
								}
							}, StoreLayer + ".BlockPanel." + index,
							StoreLayer + ".BlockPanel." + index + ".Open",
							StoreLayer + ".BlockPanel." + index + ".Open");
					});
				}

				return true;
			}

			IncrementCommandTimeout(player, 0);
			IncrementCommandTimeout(player, 1);
			IncrementCommandTimeout(player, 2);

			if (IsCommandBanned(player)) ListBannedCommandUserID.Add(player.userID);

			if (!args.HasArgs())
			{
				player.SendConsoleCommand("chat.say /store");
				return true;
			}

			return false;
		}

		private void IncrementCommandTimeout(BasePlayer player, int index)
		{
			// Инициализируем словарь для индекса, если его нет
			if (!ListTimeOutCommand.ContainsKey(index))
				ListTimeOutCommand[index] = new Dictionary<ulong, int>();

			if (!ListTimeOutCommand[index].ContainsKey(player.userID))
				ListTimeOutCommand[index][player.userID] = 1;
			else
				ListTimeOutCommand[index][player.userID]++;
		}

		private bool IsCommandBanned(BasePlayer player)
		{
			// Проверяем, инициализированы ли словари
			if (!ListTimeOutCommand.ContainsKey(0) || !ListTimeOutCommand[0].ContainsKey(player.userID))
				return false;
			if (!ListTimeOutCommand.ContainsKey(1) || !ListTimeOutCommand[1].ContainsKey(player.userID))
				return false;
			if (!ListTimeOutCommand.ContainsKey(2) || !ListTimeOutCommand[2].ContainsKey(player.userID))
				return false;

			return ListTimeOutCommand[0][player.userID] >= 10 ||
			       ListTimeOutCommand[1][player.userID] >= 20 ||
			       ListTimeOutCommand[2][player.userID] >= 30;
		}

		#endregion

		#region Command Helpers

		private void ProcessTakeCommand(BasePlayer player, int index, int basketID)
		{
			if (!_playerBaskets.TryGetValue(player.userID, out var playerBasket) ||
			    !playerBasket.BasketItems.TryGetValue(basketID, out var item))
			{
				ShowNotify(player,  Msg(player, MsgItemnotfound), true);
				return;
			}

			if (_config.Plugin.UseBuildingBlocked && player.IsBuildingBlocked())
			{
				ShowNotify(player, Msg(player, MsgBuildingBlocked), true);
				return;
			}

			if (_config.Plugin.UseCombatBlocked && NE_IsCombatBlocked(player))
			{
				ShowNotify(player, Msg(player, MsgCombatBlocked), true);
				return;
			}

			if (_config.Plugin.UseRaidBlocked && NE_IsRaidBlocked(player))
			{
				ShowNotify(player, Msg(player, MsgRaidBlocked), true);
				return;
			}

			if (item.IsBlocked)
			{
				var time = FormatTime(player, item.LeftTime);

				ShowNotify(player, Msg(player, MsgTakeItemBlocked, time), true);

				// Заменяем фон карточки на card_red.png при блокировке
				UpdateUI(player, container =>
				{
					// Удаляем старое изображение фона
					CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

					// Добавляем новое изображение card_red.png
					container.Add(new CuiElement
					{
						Parent = StoreLayer + $".BlockPanel.{index}",
						Name = StoreLayer + $".BlockPanel.{index}.CardBg",
						Components =
						{
							GetCardImageComponent(ImageCardRed, "https://storage.prostoj.store/plugin-file/card_red.png"),
							new CuiRectTransformComponent
							{
								AnchorMin = "0 0",
								AnchorMax = "1 1",
								OffsetMin = "0 0",
								OffsetMax = "0 0"
							}
						}
					});

					// Overlay без цветного фона, только текст
					container.Add(new CuiButton
						{
							RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
							Button =
							{
								FadeIn = 1f, Color = "0 0 0 0", // Прозрачный фон вместо красного
								Close = StoreLayer + ".BlockPanel." + index + ".Open",
								Command = $"UI_GameStoresRUST take {index} {basketID}"
							},
							Text =
							{
								Text = Msg(player, MsgTakeGiveBlocked, time), Align = TextAnchor.MiddleCenter,
								Font = "robotocondensed-bold.ttf", Color = "1.00 0.09 0.00 1.00", FontSize = 16
							}
						}, StoreLayer + ".BlockPanel." + index,
						StoreLayer + ".BlockPanel." + index + ".Open",
						StoreLayer + ".BlockPanel." + index + ".Open");
				});
				return;
			}

			// Уведомление убрано - статус показывается в карточке

			if (!CanRequestGetItem(basketID))
			{
				// Заменяем фон карточки на card_gray.png при ожидании
				UpdateUI(player, container =>
				{
					// Удаляем старое изображение фона
					CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

					// Добавляем новое изображение card_gray.png
					container.Add(new CuiElement
					{
						Parent = StoreLayer + $".BlockPanel.{index}",
						Name = StoreLayer + $".BlockPanel.{index}.CardBg",
						Components =
						{
							GetCardImageComponent(ImageCardGray, "https://storage.prostoj.store/plugin-file/card_gray.png"),
							new CuiRectTransformComponent
							{
								AnchorMin = "0 0",
								AnchorMax = "1 1",
								OffsetMin = "0 0",
								OffsetMax = "0 0"
							}
						}
					});

					// Overlay без цветного фона, только текст
					container.Add(new CuiButton
						{
							RectTransform =
							{
								AnchorMin = "0 0", AnchorMax = "1 1"
							},
							Button =
							{
								Color = "0 0 0 0", // Прозрачный фон вместо серого
								Close = StoreLayer + ".BlockPanel." + index + ".Open",
								Command = $"UI_GameStoresRUST take {index} {basketID}"
							},
							Text =
							{
								Text = Msg(player, MsgTakeWait), Align = TextAnchor.MiddleCenter,
								Font = "robotocondensed-bold.ttf", Color = "1 1 1 0.4", FontSize = 16
							}
						}, StoreLayer + ".BlockPanel." + index,
						StoreLayer + ".BlockPanel." + index + ".Open",
						StoreLayer + ".BlockPanel." + index + ".Open");
				});
				return;
			}

			// Заменяем фон карточки на card_gray.png при ожидании
			UpdateUI(player, container =>
			{
				// Удаляем старое изображение фона
				CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

				// Добавляем новое изображение card_gray.png
				container.Add(new CuiElement
				{
					Parent = StoreLayer + $".BlockPanel.{index}",
					Name = StoreLayer + $".BlockPanel.{index}.CardBg",
					Components =
					{
						GetCachedImageComponent("card_gray", "https://storage.prostoj.store/plugin-file/card_gray.png"),
						new CuiRectTransformComponent
						{
							AnchorMin = "0 0",
							AnchorMax = "1 1",
							OffsetMin = "0 0",
							OffsetMax = "0 0"
						}
					}
				});

				// Overlay без цветного фона, только текст
				container.Add(new CuiButton
					{
						RectTransform =
						{
							AnchorMin = "0 0", AnchorMax = "1 1"
						},
						Button =
						{
							Color = "0 0 0 0", // Прозрачный фон вместо серого
							Close = StoreLayer + ".BlockPanel." + index + ".Open",
							Command = $"UI_GameStoresRUST take {index} {basketID}"
						},
						Text =
						{
							Text = Msg(player, MsgTakeWait), Align = TextAnchor.MiddleCenter,
							Font = "robotocondensed-bold.ttf", Color = "1 1 1 0.4", FontSize = 24
						}
					}, StoreLayer + ".BlockPanel." + index,
					StoreLayer + ".BlockPanel." + index + ".Open",
					StoreLayer + ".BlockPanel." + index + ".Open");
			});

			RequestGetItem(player, basketID, index);
		}

		private void HandleTakeResponse(BasePlayer player, int index, int basketID, int responseCode, string response)
		{
			switch (responseCode)
			{
				case 0:
					PrintError("Api does not responded to a request");

					if (player != null)
					{
						ShowNotify(player, Msg(player, MsgUnexpectederror), true);
						CuiHelper.DestroyUi(player, StoreLayer);
					}

					break;
				case 200:
					var data =
						JsonConvert.DeserializeObject<Dictionary<string, object>>(response,
							new KeyValuesConverter());
					if (data == null || !data.ContainsKey("data"))
					{
						// Показываем уведомление об ошибке через Notify API
						ShowNotify(player, Msg(player, MsgTakeGiveError), true);

						// Заменяем фон карточки на card_red.png при ошибке
						UpdateUI(player, container =>
						{
							// Удаляем старое изображение фона
							CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

							// Добавляем новое изображение card_red.png
							container.Add(new CuiElement
							{
								Parent = StoreLayer + $".BlockPanel.{index}",
								Name = StoreLayer + $".BlockPanel.{index}.CardBg",
								Components =
								{
									GetCardImageComponent(ImageCardRed, "https://storage.prostoj.store/plugin-file/card_red.png"),
									new CuiRectTransformComponent
									{
										AnchorMin = "0 0",
										AnchorMax = "1 1",
										OffsetMin = "0 0",
										OffsetMax = "0 0"
									}
								}
							});

							// Overlay без цветного фона, только текст
							container.Add(new CuiButton
								{
									RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
									Button =
									{
										FadeIn = 1f, Color = "0 0 0 0", // Прозрачный фон вместо красного
										Close = StoreLayer + ".BlockPanel." + index + ".Open",
										Command = $"UI_GameStoresRUST take {index} {basketID}"
									},
									Text =
									{
										Text = Msg(player, MsgTakeGiveError), Align = TextAnchor.MiddleCenter,
										Font = "robotocondensed-bold.ttf", Color = "1.00 0.09 0.00 1.00", FontSize = 16
									}
								}, StoreLayer + ".BlockPanel." + index,
								StoreLayer + ".BlockPanel." + index + ".Open",
								StoreLayer + ".BlockPanel." + index + ".Open");
						});
						return;
					}

					Request("baskets.makeIssued", new Dictionary<string, string>
						{
							{"steamId", player.UserIDString},
							{"basketId", basketID.ToString()}
						},
						(code, newResponse) =>
						{
							if (code != 200 ||
							    JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() !=
							    "success")
							{
								// Показываем уведомление об ошибке через Notify API
								ShowNotify(player, Msg(player, MsgTakeGiveError), true);

								// Заменяем фон карточки на card_red.png при ошибке
								UpdateUI(player, container =>
								{
									// Удаляем старое изображение фона
									CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

									// Добавляем новое изображение card_red.png
									container.Add(new CuiElement
									{
										Parent = StoreLayer + $".BlockPanel.{index}",
										Name = StoreLayer + $".BlockPanel.{index}.CardBg",
										Components =
										{
											GetCachedImageComponent("card_red", "https://storage.prostoj.store/plugin-file/card_red.png"),
											new CuiRectTransformComponent
											{
												AnchorMin = "0 0",
												AnchorMax = "1 1",
												OffsetMin = "0 0",
												OffsetMax = "0 0"
											}
										}
									});

									// Overlay без цветного фона, только текст
									container.Add(new CuiButton
										{
											RectTransform =
												{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
											Button =
											{
												FadeIn = 1f, Color = "0 0 0 0", // Прозрачный фон вместо красного
												Close = StoreLayer + ".BlockPanel." + index + ".Open",
												Command = $"UI_GameStoresRUST take {index} {basketID}"
											},
											Text =
											{
												Text = Msg(player, MsgTakeGiveError),
												Align = TextAnchor.MiddleLeft,
												Font = "robotocondensed-bold.ttf", Color = "1.00 0.09 0.00 1.00",
												FontSize = 16
											}
										}, StoreLayer + ".BlockPanel." + index,
										StoreLayer + ".BlockPanel." + index + ".Open",
										StoreLayer + ".BlockPanel." + index + ".Open");
								});
								return;
							}

							ProcessTake(player, data["data"] as Dictionary<string, object>);
						}, player);

					// Показываем уведомление об успешной выдаче через Notify API
					ShowNotify(player, Msg(player, MsgTakeGiveSuccess));

					// Заменяем фон карточки на card_success.png при успешном получении
					UpdateUI(player, container =>
					{
						// Удаляем старое изображение фона
						CuiHelper.DestroyUi(player, StoreLayer + $".BlockPanel.{index}.CardBg");

						// Добавляем новое изображение card_success.png
						container.Add(new CuiElement
						{
							Parent = StoreLayer + $".BlockPanel.{index}",
							Name = StoreLayer + $".BlockPanel.{index}.CardBg",
							Components =
							{
								GetCardImageComponent(ImageCardSuccess, "https://storage.prostoj.store/plugin-file/card_success.png"),
								new CuiRectTransformComponent
								{
									AnchorMin = "0 0",
									AnchorMax = "1 1",
									OffsetMin = "0 0",
									OffsetMax = "0 0"
								}
							}
						});

						// Overlay без цветного фона, только текст
						container.Add(new CuiButton
							{
								RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
								Button = {FadeIn = 1f, Color = "0 0 0 0"}, // Прозрачный фон вместо зеленого
								Text =
								{
									Text = Msg(player, MsgTakeGiveSuccess), Align = TextAnchor.MiddleCenter,
									Font = "robotocondensed-bold.ttf", Color = "0 1 0.42 1", FontSize = 16
								}
							}, StoreLayer + ".BlockPanel." + index, StoreLayer + ".BlockPanel." + index + ".Open",
							StoreLayer + ".BlockPanel." + index + ".Open");
					});

					if (_playerBaskets.ContainsKey(player.userID) &&
					    _playerBaskets[player.userID].BasketItems.ContainsKey(basketID))
						_playerBaskets[player.userID].BasketItems.Remove(basketID);
					break;
				case 404:
					ShowNotify(player, Msg(player, MsgUnexpectederror), true);
					CuiHelper.DestroyUi(player, StoreLayer);
					break;
			}
		}

		#endregion

		private void FetchShopUrl()
		{
			initialization = true;
			timer.In(5, () => { initialization = false; });

			Request($"store.pluginInfo", null, HandleFetchShopUrlResponse);
		}

		#region Networking

		private const int timeout = 30; // 30 seconds

		private void Request(string method, Dictionary<string, string> args, Action<int, string> callback,
			BasePlayer player = null)
		{
			if (!SecureConnection && BestApiLink.Contains("https://"))
				BestApiLink = BestApiLink.Replace("https://", "http://");

			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			// API определяет сервер по IP и порту, store_id не нужен
			string queryParams = $"?server_ip={serverIp}&server_port={serverPort}";
			string fullUrl = BestApiLink + method + queryParams;

			// Добавляем steamId в body для методов, требующих авторизации
			if (player != null && args != null && !args.ContainsKey("steamId") && !args.ContainsKey("steam_id"))
			{
				args["steamId"] = player.UserIDString;
			}


            _activeCoroutines.Add(ServerMgr.Instance.StartCoroutine(WebPostRequestAsync(fullUrl, args,
				(code, response) =>
				{
					if (Instance == null) return;

					var anyError = false;

					switch (code)
					{
						case 200:
							// success
							break;
						case 404:
							break;
						default:
						{
							anyError = true;

							errorsReq++;

							if (errorsReq >= MaxErrorsReq)
							{
								errorsReq = 0;

								TryChangeLink();
							}

							break;
						}
					}

					if (SecureConnection && response.Contains("The authentication or decryption has failed."))
					{
						SecureConnection = false;
						BestApiLink = BestApiLink.Replace("https://", "http://");

						Request(method, args, callback, player);
						return;
					}

					if (Initialized && !anyError)
						try
						{
							var maintenanceResult = JsonConvert.DeserializeObject<MaintenanceResult>(response);
							if (maintenanceResult is {maintenance: true})
							{
								if (player != null) CuiHelper.DestroyUi(player, StoreLayer);

								maintenance = true;
							}
							else
							{
								maintenance = false;
							}
						}
						catch (Exception ex)
						{
							LogToFile("Errors",
								$"{DateTime.Now.ToShortTimeString()}| JsonError | Response: '{response}'",
								this);
							return;
						}

					callback?.Invoke(code, response);
				})));
		}

		private void RequestSupport(string method, Dictionary<string, string> args, Action<int, string> callback,
			BasePlayer player = null)
		{
			if (!SecureConnection && BestApiLink.Contains("https://"))
				BestApiLink = BestApiLink.Replace("https://", "http://");

			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			// API определяет сервер по IP и порту, store_id не нужен
			string queryParams = $"?server_ip={serverIp}&server_port={serverPort}";

			// Используем базовый URL без /game-stores/ для поддержки
			string baseUrl = BestApiLink.Replace("/v1/game-stores/", "/v1/");
			if (!baseUrl.EndsWith("/"))
				baseUrl += "/";
			string fullUrl = baseUrl + method + queryParams;

			// Добавляем steamId в body для методов, требующих авторизации
			if (player != null && args != null && !args.ContainsKey("steamId") && !args.ContainsKey("steam_id"))
			{
				args["steamId"] = player.UserIDString;
			}

			// Логирование запроса
			string argsStr = args != null ? string.Join(", ", args.Select(a => $"{a.Key}={a.Value}")) : "empty";
			PrintWarning($"[Support] Request: {method} | URL: {fullUrl} | Args: {argsStr} | Player: {(player != null ? player.UserIDString : "null")}");

			_activeCoroutines.Add(ServerMgr.Instance.StartCoroutine(WebPostRequestAsync(fullUrl, args,
				(code, response) =>
				{
					if (Instance == null) return;

					// Логирование ответа
					PrintWarning($"[Support] Response: {method} | Code: {code} | Response length: {(response?.Length ?? 0)}");
					if (code != 200 && code != 201)
					{
						PrintError($"[Support] Error response: {response}");
					}
					else if (response != null && response.Length > 0)
					{
						PrintWarning($"[Support] Success response preview: {response.Substring(0, Math.Min(200, response.Length))}");
					}

					var anyError = false;

					switch (code)
					{
						case 200:
						case 201:
							// success
							break;
						case 404:
							PrintWarning($"[Support] 404 Not Found for method: {method}");
							break;
						default:
						{
							anyError = true;
							PrintError($"[Support] Error code {code} for method: {method}");
							break;
						}
					}

					if (SecureConnection && response.Contains("The authentication or decryption has failed."))
					{
						PrintWarning($"[Support] SSL error, switching to HTTP");
						SecureConnection = false;
						baseUrl = baseUrl.Replace("https://", "http://");
						fullUrl = baseUrl + method + queryParams;

						RequestSupport(method, args, callback, player);
						return;
					}

					if (Initialized && !anyError)
						try
						{
							var maintenanceResult = JsonConvert.DeserializeObject<MaintenanceResult>(response);
							if (maintenanceResult is {maintenance: true})
							{
								if (player != null) CuiHelper.DestroyUi(player, StoreLayer);

								maintenance = true;
							}
							else
							{
								maintenance = false;
							}
						}
						catch (Exception ex)
						{
							// Игнорируем ошибки парсинга maintenance для поддержки
							PrintWarning($"[Support] Maintenance check failed (non-critical): {ex.Message}");
						}

					callback?.Invoke(code, response);
				})));
		}

		private void RequestToPay(string method, Dictionary<string, string> args, Action<int, string> callback)
        {
			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			// API определяет сервер по IP и порту, store_id не нужен
			string queryParams = $"?server_ip={serverIp}&server_port={serverPort}";
			string fullUrl = PayApiLink + method + queryParams;


            _activeCoroutines.Add(ServerMgr.Instance.StartCoroutine(WebPostRequestAsync(fullUrl, args,
                (code, response) =>
                {
                    if (Instance == null) return;

                    var anyError = false;
                    switch (code)
                    {
                        case 200:
                            // success
                            break;
                        case 400:
                            // success
                            break;
                        case 404:
                            break;
                        default:
                            {
                                anyError = true;
                                break;
                            }
                    }
                    if (Initialized && !anyError)
                        try
                        {
                            var maintenanceResult = JsonConvert.DeserializeObject<MaintenanceResult>(response);
                            if (maintenanceResult is { maintenance: true })
                            {
                                maintenance = true;
                            }
                            else
                            {
                                maintenance = false;
                            }
                        }
                        catch (Exception ex)
                        {
                            LogToFile("Errors",
                                $"{DateTime.Now.ToShortTimeString()}| Error req to pay | JsonError | Response: '{response}'",
                                this);
                            return;
                        }

                    callback?.Invoke(code, response);
                })));
        }

		private void CheckRequest(string url, string method, Action<int, string> callback)
		{
			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			// API определяет сервер по IP и порту, store_id не нужен
			string queryParams = $"?server_ip={serverIp}&server_port={serverPort}";
			string fullUrl = url + method + queryParams;


            _activeCoroutines.Add(ServerMgr.Instance.StartCoroutine(WebPostRequestAsync(fullUrl, null,
				(code, response) => callback?.Invoke(code, response))));
		}

		private IEnumerator WebPostRequestAsync(string url, Dictionary<string, string> bodyFields,
			Action<int, string> onRequestComplete = null)
		{
			using var webRequest = UnityWebRequest.Post(url, bodyFields ?? new Dictionary<string, string>());

			webRequest.timeout = timeout;

			_activeRequests.Add(webRequest);

			foreach (var check in reqHeaders)
				webRequest.SetRequestHeader(check.Key, check.Value);

			// Логирование запроса
			var headersStr = string.Join(", ", reqHeaders.Select(h => $"{h.Key}={h.Value}"));
			var bodyStr = bodyFields != null && bodyFields.Count > 0
				? string.Join(", ", bodyFields.Select(b => $"{b.Key}={b.Value}"))
				: "empty";

			yield return webRequest.SendWebRequest();

			_activeRequests.Remove(webRequest);

			var responseCode = (int) webRequest.responseCode;
			var responseText = webRequest.downloadHandler != null ? webRequest.downloadHandler.text : string.Empty;


			onRequestComplete?.Invoke(responseCode, responseText);
		}

		#region Networking Helpers

		private void DestroyRequestCoroutines()
		{
			foreach (var coroutine in _activeCoroutines)
				if (coroutine != null)
					ServerMgr.Instance.StopCoroutine(coroutine);

			_activeCoroutines.Clear();
		}

		private void DisposeActiveRequests()
		{
			for (var index = _activeRequests.Count - 1; index >= 0; index--)
				try
				{
					_activeRequests[index]?.Dispose();
				}
				catch
				{
					// ignored
				}
		}

		#endregion

		#endregion

		private void LogAction(BasePlayer player, string text, bool printToConsole = false, bool printError = false)
		{
#if TESTING
			// SayDebug($"[LogAction] {text}");
#endif

			LogToFile("!global",
				$"{DateTime.Now.ToShortTimeString()} {(player != null ? "[" + player.userID + "]" : " ")}{text}", this);

			if (printToConsole)
				if (printError)
					PrintError($"{text}");
				else
					PrintWarning($"{text}");
			if (player != null)
				LogPlayerAction(player, text);
		}

		private void LogPlayerAction(BasePlayer player, string text)
		{
			LogToFile($"{player.userID}",
				$"{DateTime.Now.ToShortTimeString()} {text}", this);
		}

		private static double CurrentTime()
		{
			return DateTime.UtcNow.Subtract(new DateTime(1970, 1, 1)).TotalSeconds;
		}

		#endregion

		#region Lang

		private const string
			MsgTimeDAYS = "TIME.DAYS",
			MsgTimeHOURS = "TIME.HOURS",
			MsgTimeMINUTES = "TIME.MINUTES",
			MsgTimeSECONDS = "TIME.SECONDS",
			MsgBuildingBlocked = "MsgBuildingBlocked",
			MsgCombatBlocked = "MsgCombatBlocked",
			MsgRaidBlocked = "MsgRaidBlocked",
			Maintenance = "Maintenance",
			MsgPluginnotinitialized = "PluginNotInitialized",
			MsgItemnotfound = "ItemNotFound",
			MsgUnexpectederror = "UnexpectedError",
			MsgWaitpreviousaction = "WaitPreviousAction",
			MsgBasket = "BASKET",
			MsgExit = "EXIT",
			MsgBasketDescription = "BASKET.DESCRIPTION",
			MsgBasketEmpty = "BASKET.EMPTY",
			MsgHelp = "HELP",
			MsgSupport = "SUPPORT",
			MsgRequestProcessing = "REQUEST.PROCESSING",
			MsgBasketUnavailable = "BASKET.UNAVAILABLE",
			MsgBasketNoAuth = "BASKET.NO.AUTH",
			MsgUserManual = "USER.MANUAL",
			MsgUserManualDescription = "USER.MANUAL.DESCRIPTION",
			MsgUserManualBalance = "USER.MANUAL.BALANCE",
			MsgTakeItemBlocked = "TAKE.ITEM.BLOCKED",
			MsgTakeRequestProcessing = "TAKE.REQUEST.PROCESSING",
			MsgTakeLastRequestProcessing = "TAKE.LAST.REQUEST.PROCESSING",
			MsgTakeWait = "TAKE.WAIT",
			MsgTakeGiveBlocked = "TAKE.GIVE.BLOCKED",
			MsgTakeGiveError = "TAKE.GIVE.ERROR",
			MsgTakeGiveSuccess = "TAKE.GIVE.SUCCESS",
			MsgTakeGiveErrorNotify = "TAKE.GIVE.ERROR.NOTIFY",
			MsgTakeGiveFeet = "TAKE.GIVE.FEET",
			MsgTakeGiveInventory = "TAKE.GIVE.INVENTORY",
			MsgTakeGiveCommand = "TAKE.GIVE.COMMAND",
			MsgTakeGiveBlueprintFeet = "TAKE.GIVE.BLUEPRINT.FEET",
			MsgTakeGiveBlueprintInventory = "TAKE.GIVE.BLUEPRINT.INVENTORY",
			MsgManyRequests = "MANY.REQUESTS",
			MsgPlayerFloodBlock = "PlayerFloodBlock";

		protected override void LoadDefaultMessages()
		{
			lang.RegisterMessages(new Dictionary<string, string>
			{
				{
					Maintenance,
					"We are currently undergoing technical work, please try later."
				},

				{
					MsgBuildingBlocked,
					"You will not be able to open a store if you are not allowed to build in this zone!"
				},
				{MsgCombatBlocked, "You will not be able to open a store if you you are raid blocked!"},
				{MsgRaidBlocked, "You will not be able to open a store if you you are combat blocked!"},

				// Global
				{MsgTimeDAYS, "d."},
				{MsgTimeHOURS, "h."},
				{MsgTimeMINUTES, "m."},
				{MsgTimeSECONDS, "s."},


				// Chat messages
				{MsgPluginnotinitialized, "The store plugin is not launched correctly"},
				{
					MsgPlayerFloodBlock,
					"You have made too many requests. Relax a little, soon the ability to perform the action will return to you."
				},
				{MsgItemnotfound, "Item not found, try restarting your cart"},
				{MsgUnexpectederror, "Unexpected error from server side, sorry!"},
				{MsgWaitpreviousaction, "Please wait for the <<end>> of the previous step!"},

				// UI
				{MsgBasket, "SERVER BASKET"},
				{MsgExit, MsgExit},
				{MsgBasketDescription, "This is your shopping cart, you can pick them up at any time"},
				{MsgBasketEmpty, "Basket empty"},
				{MsgHelp, "HELP"},
				{MsgSupport, "SUPPORT"},
				{MsgRequestProcessing, "Please wait, we are processing your request ..."},
				{MsgBasketUnavailable, "Cart is temporarily unavailable, please try again later"},
				{
					MsgBasketNoAuth, "You are not authorized in the store!\n" +
					                 "You can find a link to authorization in the 'HELP' section"
				},


				{MsgUserManual, "USER'S MANUAL"},
				{
					MsgUserManualDescription, "Here you can pick up the products purchased from our store!\n" +
					                          "You can replenish your account in various ways: e-wallets, cards, etc.\n" +
					                          "Authorization in the store takes place using your STEAM account"
				},

				{
					MsgUserManualBalance,
					"\n<size=18>At the first authorization, you will receive a gift of <b>{0} rubles</b>!</size>"
				},
				{
					MsgTakeItemBlocked, "You cannot pick up this item from your cart!\n" +
					                    "Before its unlocking, there are: {0}"
				},
				{MsgTakeRequestProcessing, "Please wait, your request is being processed!"},
				{MsgTakeLastRequestProcessing, "Please wait, your previous request is still being processed!"},
				{MsgTakeWait, "WAIT"},
				{MsgTakeGiveBlocked, "BLOCKED\n{0}"},
				{MsgTakeGiveError, "ERROR\nRECEIVING"},
				{
					MsgTakeGiveErrorNotify, "An unexpected error occured, please try again later!\n" +
					                        "Your item is safe, don't worry!"
				},
				{MsgTakeGiveSuccess, "SUCCESSFULLY\nRECEIVED"},
				{
					MsgTakeGiveFeet, "You have successfully obtained an item  › <size=20>{0}</size> ‹\n" +
					                 "You don't have enough space in your inventory, <b>the item was thrown</b> at your feet!"
				},
				{MsgTakeGiveInventory, "You have successfully obtained an item  › <size=20>{0}</size> ‹"},
				{
					MsgTakeGiveCommand, "You have successfully obtained an item  › <size=20>{0}</size> ‹\n" +
					                    "New privileges are now available to you!"
				},
				{
					MsgTakeGiveBlueprintFeet,
					"You have successfully obtained the item blueprint  › <size=20>{0}</size> ‹\n" +
					"You don't have enough space in your inventory, <b>the item was thrown</b> at your feet!"
				},
				{
					MsgTakeGiveBlueprintInventory,
					"You have successfully obtained the item blueprint  › <size=20>{0}</size> ‹"
				},
				{
					MsgManyRequests, "You are making too many server requests!\n" +
					                 "Wait <b>one second</b>!"
				}
			}, this);

			lang.RegisterMessages(new Dictionary<string, string>
			{
				{
					Maintenance,
					"В данный момент у нас проходят технические работы, попробуйте пожалуйста позже."
				},

				{MsgBuildingBlocked, "Вы не можете открыть магазин, находясь в зоне блокировки строительства!"},
				{MsgCombatBlocked, "Вы не можете открыть магазин, находясь в зоне блокировки боя!"},
				{MsgRaidBlocked, "Вы не можете открыть магазин, находясь в зоне блокировки рейда!"},

				// Global
				{MsgTimeDAYS, "дн."},
				{MsgTimeHOURS, "ч."},
				{MsgTimeMINUTES, "мин."},
				{MsgTimeSECONDS, "сек."},

				// Chat messages
				{MsgPluginnotinitialized, "Плагин магазина запущен некорректно"},
				{
					MsgPlayerFloodBlock,
					"Вы сделали слишком много запросов. Отдохните немного, скоро возможность выполнять действие к вам вернется."
				},
				{MsgItemnotfound, "Предмет не найден, попробуйте перезапустить корзину"},
				{MsgUnexpectederror, "Непредвиденная ошибка со стороны сервера, просим прощения!"},
				{MsgWaitpreviousaction, "Пожалуйста, дождитесь <<окончания>> предыдущего действия!"},

				// UI
				{MsgBasket, "КОРЗИНА СЕРВЕРА"},
				{MsgExit, "ВЫХОД"},
				{MsgBasketDescription, "Это ваша корзина с покупками, вы можете забрать предметы в любой момент...\nЧтобы купить товар нужно перейти на сайт prostoj.store"},
				{MsgBasketEmpty, "Ваша корзина пуста"},
				{MsgHelp, "ПОМОЩЬ"},
				{MsgSupport, "ПОДДЕРЖКА"},
				{MsgRequestProcessing, "Подождите, мы обрабатываем ваш запрос..."},
				{MsgBasketUnavailable, "Корзина временно недоступна, попробуйте позже"},
				{
					MsgBasketNoAuth, "Вы не авторизованы в магазине!\n" +
					                 "Ссылку на авторизацию вы можете найти в разделе 'ПОМОЩЬ'"
				},


				{MsgUserManual, "ИНСТРУКЦИЯ ПОЛЬЗОВАТЕЛЯ"},
				{
					MsgUserManualDescription, "Здесь вы можете забрать товары приобретенные у нас в магазине!\n" +
					                          "Пополнить счёт можно различными способами: электронные кошельки, карты и т.д.\n" +
					                          "Авторизация в магазине происходит при помощи вашего аккаунта STEAM"
				},

				{
					MsgUserManualBalance,
					"\n<size=18>При первой авторизации вы получите в подарок <b>{0} рублей</b>!</size>"
				},
				{
					MsgTakeItemBlocked, "Вы не можете забрать этот предмет из корзины!\n" +
					                    "До его разблокировки осталось: {0}"
				},
				{MsgTakeRequestProcessing, "Подождите, ваш запрос обрабатывается!"},
				{MsgTakeLastRequestProcessing, "Подождите, ваш предыдущий запрос ещё обрабатывается!"},
				{MsgTakeWait, "ПОДОЖДИТЕ"},
				{MsgTakeGiveBlocked, "ЗАБЛОКИРОВАНО\n{0}"},
				{MsgTakeGiveError, "ОШИБКА\nПОЛУЧЕНИЯ"},
				{
					MsgTakeGiveErrorNotify, "Произошла непредвиденная ошибка, попробуйте позже!\n" +
					                        "Ваш предмет в безопасности, не переживайте!"
				},
				{MsgTakeGiveSuccess, "УСПЕШНО\nПОЛУЧЕНО"},
				{
					MsgTakeGiveFeet, "Вы успешно получили предмет  › <size=20>{0}</size> ‹\n" +
					                 "У вас недостаточно места в инвентаре, <b>предмет брошен</b> под ноги!"
				},
				{MsgTakeGiveInventory, "Вы успешно получили предмет  › <size=20>{0}</size> ‹"},
				{
					MsgTakeGiveCommand, "Вы успешно получили предмет  › <size=20>{0}</size> ‹\n" +
					                    "Теперь вам доступны новые привилегии!"
				},
				{
					MsgTakeGiveBlueprintFeet, "Вы успешно получили рецепт предмета  › <size=20>{0}</size> ‹\n" +
					                          "У вас недостаточно места в инвентаре, <b>предмет брошен</b> под ноги!"
				},
				{MsgTakeGiveBlueprintInventory, "Вы успешно получили рецепт предмета  › <size=20>{0}</size> ‹"},
				{
					MsgManyRequests, "Вы делаете слишком много запросов к серверу!\n" +
					                 "Подождите <b>одну секунду</b>!"
				}
			}, this, "ru");
		}

		private string Msg(BasePlayer player, string key, params object[] args)
		{
			return string.Format(lang.GetMessage(key, this, player != null ? player.UserIDString : null), args);
		}

		private void Reply(BasePlayer player, string key, params object[] args)
		{
			player.ChatMessage(Msg(player, key, args));
		}

		private string FormatTime(BasePlayer player, double leftTime)
		{
			var span = TimeSpan.FromSeconds(leftTime);

			var text = string.Empty;
			if (span.Days >= 1)
				text += $"{span.Days} " + Msg(player, MsgTimeDAYS) + " ";
			if (span.Hours >= 1)
				text += $"{span.Hours} " + Msg(player, MsgTimeHOURS) + " ";
			if (span.Minutes >= 1)
				text += $"{span.Minutes} " + Msg(player, MsgTimeMINUTES) + " ";
			if (span.Seconds >= 1)
				text += $"{span.Seconds} " + Msg(player, MsgTimeSECONDS);

			return text;
		}

		/// <summary>
		/// Форматирует баланс в денежном формате с разделителями тысяч
		/// </summary>
		private string FormatBalance(object balanceObj)
		{
			if (balanceObj == null) return "0";

			// Пробуем разные типы данных
			if (balanceObj is int intBalance)
			{
				return intBalance.ToString("N0", System.Globalization.CultureInfo.InvariantCulture).Replace(",", " ");
			}

			if (balanceObj is double doubleBalance)
			{
				return ((long)Math.Round(doubleBalance)).ToString("N0", System.Globalization.CultureInfo.InvariantCulture).Replace(",", " ");
			}

			if (balanceObj is float floatBalance)
			{
				return ((long)Math.Round(floatBalance)).ToString("N0", System.Globalization.CultureInfo.InvariantCulture).Replace(",", " ");
			}

			if (balanceObj is long longBalance)
			{
				return longBalance.ToString("N0", System.Globalization.CultureInfo.InvariantCulture).Replace(",", " ");
			}

			// Пробуем распарсить как строку
			string balanceStr = balanceObj.ToString();
			if (double.TryParse(balanceStr, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var parsedBalance))
			{
				return ((long)Math.Round(parsedBalance)).ToString("N0", System.Globalization.CultureInfo.InvariantCulture).Replace(",", " ");
			}

			return balanceStr;
		}

		#endregion

		#region Additional

		private static readonly Dictionary<int, string> IDToShortName = new()
		{
			[-1461508848] = "rifle.ak", [2115555558] = "ammo.handmade.shell", [-533875561] = "ammo.pistol",
			[1621541165] = "ammo.pistol.fire", [-422893115] = "ammo.pistol.hv", [815896488] = "ammo.rifle",
			[805088543] = "ammo.rifle.explosive", [449771810] = "ammo.rifle.incendiary", [1152393492] = "ammo.rifle.hv",
			[1578894260] = "ammo.rocket.basic", [1436532208] = "ammo.rocket.fire", [542276424] = "ammo.rocket.hv",
			[1594947829] = "ammo.rocket.smoke", [-1035059994] = "ammo.shotgun", [1818890814] = "ammo.shotgun.fire",
			[1819281075] = "ammo.shotgun.slug", [1685058759] = "antiradpills", [93029210] = "apple",
			[-1565095136] = "apple.spoiled", [-1775362679] = "arrow.bone", [-1775249157] = "arrow.fire",
			[-1280058093] = "arrow.hv", [-420273765] = "arrow.wooden", [563023711] = "autoturret",
			[790921853] = "axe.salvaged", [-337261910] = "bandage", [498312426] = "barricade.concrete",
			[504904386] = "barricade.metal", [-1221200300] = "barricade.sandbags", [510887968] = "barricade.stone",
			[-814689390] = "barricade.wood", [1024486167] = "barricade.woodwire", [2021568998] = "battery.small",
			[97329] = "bbq", [1046072789] = "trap.bear", [97409] = "bed", [-1480119738] = "tool.binoculars",
			[1611480185] = "black.raspberries", [-1386464949] = "bleach", [93832698] = "blood",
			[-1063412582] = "blueberries", [-1887162396] = "blueprintbase", [-55660037] = "rifle.bolt",
			[919780768] = "bone.club", [-365801095] = "bone.fragments", [68998734] = "botabag",
			[-853695669] = "bow.hunting", [271534758] = "box.wooden.large", [-770311783] = "box.wooden",
			[-1192532973] = "bucket.water", [-307490664] = "building.planner", [707427396] = "burlap.shirt",
			[707432758] = "burlap.shoes", [-2079677721] = "cactusflesh", [-1342405573] = "tool.camera",
			[-139769801] = "campfire", [-1043746011] = "can.beans", [2080339268] = "can.beans.empty",
			[-171664558] = "can.tuna", [1050986417] = "can.tuna.empty", [-1693683664] = "candycaneclub",
			[523409530] = "candycane", [1300054961] = "cctv.camera", [-2095387015] = "ceilinglight",
			[1428021640] = "chainsaw", [94623429] = "chair", [1436001773] = "charcoal", [1711323399] = "chicken.burned",
			[1734319168] = "chicken.cooked", [-1658459025] = "chicken.raw", [-726947205] = "chicken.spoiled",
			[-341443994] = "chocholate", [1540879296] = "xmasdoorwreath", [94756378] = "cloth", [3059095] = "coal",
			[3059624] = "corn", [2045107609] = "clone.corn", [583366917] = "seed.corn", [2123300234] = "crossbow",
			[1983936587] = "crude.oil", [1257201758] = "cupboard.tool", [-1144743963] = "diving.fins",
			[-1144542967] = "diving.mask", [-1144334585] = "diving.tank", [1066729526] = "diving.wetsuit",
			[-1598790097] = "door.double.hinged.metal", [-933236257] = "door.double.hinged.toptier",
			[-1575287163] = "door.double.hinged.wood", [-2104481870] = "door.hinged.metal",
			[-1571725662] = "door.hinged.toptier", [1456441506] = "door.hinged.wood", [1200628767] = "door.key",
			[-778796102] = "door.closer", [1526866730] = "xmas.door.garland", [1925723260] = "dropbox",
			[1891056868] = "ducttape", [1295154089] = "explosive.satchel", [498591726] = "explosive.timed",
			[1755466030] = "explosives", [726730162] = "facialhair.style01", [-1034048911] = "fat.animal",
			[252529905] = "femalearmpithair.style01", [471582113] = "femaleeyebrow.style01",
			[-1138648591] = "femalepubichair.style01", [305916740] = "female_hairstyle_01",
			[305916742] = "female_hairstyle_03", [305916744] = "female_hairstyle_05", [1908328648] = "fireplace.stone",
			[-2078972355] = "fish.cooked", [-533484654] = "fish.raw", [1571660245] = "fishingrod.handmade",
			[1045869440] = "flamethrower", [1985408483] = "flameturret", [97513422] = "flare",
			[1496470781] = "flashlight.held", [1229879204] = "weapon.mod.flashlight", [-1722829188] = "floor.grill",
			[1849912854] = "floor.ladder.hatch", [-1266285051] = "fridge", [-1749787215] = "boots.frog",
			[28178745] = "lowgradefuel", [-505639592] = "furnace", [1598149413] = "furnace.large",
			[-1779401418] = "gates.external.high.stone", [-57285700] = "gates.external.high.wood", [98228420] = "gears",
			[1422845239] = "geiger.counter", [277631078] = "generator.wind.scrap", [115739308] = "burlap.gloves",
			[-522149009] = "gloweyes", [3175989] = "glue", [718197703] = "granolabar", [384204160] = "grenade.beancan",
			[-1308622549] = "grenade.f1", [-217113639] = "fun.guitar", [-1580059655] = "gunpowder",
			[-1832205789] = "male_hairstyle_01", [305916741] = "female_hairstyle_02",
			[936777834] = "attire.hide.helterneck", [-1224598842] = "hammer", [-1976561211] = "hammer.salvaged",
			[-1406876421] = "hat.beenie", [-1397343301] = "hat.boonie", [1260209393] = "bucket.helmet",
			[-1035315940] = "burlap.headwrap", [-1381682752] = "hat.candle", [696727039] = "hat.cap",
			[-2128719593] = "coffeecan.helmet", [-1178289187] = "deer.skull.mask", [1351172108] = "heavy.plate.helmet",
			[-450738836] = "hat.miner", [-966287254] = "attire.reindeer.headband", [340009023] = "riot.helmet",
			[124310981] = "hat.wolf", [1501403549] = "wood.armor.helmet", [698310895] = "hatchet",
			[523855532] = "hazmatsuit", [2045246801] = "clone.hemp", [583506109] = "seed.hemp",
			[-148163128] = "attire.hide.boots", [-132588262] = "attire.hide.skirt", [-1666761111] = "attire.hide.vest",
			[-465236267] = "weapon.mod.holosight", [-1211618504] = "hoodie", [2133577942] = "hq.metal.ore",
			[-1014825244] = "humanmeat.burned", [-991829475] = "humanmeat.cooked", [-642008142] = "humanmeat.raw",
			[661790782] = "humanmeat.spoiled", [-1440143841] = "icepick.salvaged", [569119686] = "bone.armor.suit",
			[1404466285] = "heavy.plate.jacket", [-1616887133] = "jacket.snow", [-1167640370] = "jacket",
			[-1284735799] = "jackolantern.angry", [-1278649848] = "jackolantern.happy", [776005741] = "knife.bone",
			[108061910] = "ladder.wooden.wall", [255101535] = "trap.landmine", [-51678842] = "lantern",
			[-789202811] = "largemedkit", [516382256] = "weapon.mod.lasersight", [50834473] = "leather",
			[-975723312] = "lock.code", [1908195100] = "lock.key", [-1097452776] = "locker", [146685185] = "longsword",
			[-1716193401] = "rifle.lr300", [193190034] = "lmg.m249", [371156815] = "pistol.m92", [3343606] = "mace",
			[825308669] = "machete", [830965940] = "mailbox", [1662628660] = "male.facialhair.style02",
			[1662628661] = "male.facialhair.style03", [1662628662] = "male.facialhair.style04",
			[-1832205788] = "male_hairstyle_02", [-1832205786] = "male_hairstyle_04",
			[1625090418] = "malearmpithair.style01", [-1269800768] = "maleeyebrow.style01",
			[429648208] = "malepubichair.style01", [-1832205787] = "male_hairstyle_03",
			[-1832205785] = "male_hairstyle_05", [107868] = "map", [997973965] = "mask.balaclava",
			[-46188931] = "mask.bandana", [-46848560] = "metal.facemask", [-2066726403] = "bearmeat.burned",
			[-2043730634] = "bearmeat.cooked", [1325935999] = "bearmeat", [-225234813] = "deermeat.burned",
			[-202239044] = "deermeat.cooked", [-322501005] = "deermeat.raw", [-1851058636] = "horsemeat.burned",
			[-1828062867] = "horsemeat.cooked", [-1966381470] = "horsemeat.raw", [968732481] = "meat.pork.burned",
			[991728250] = "meat.pork.cooked", [-253819519] = "meat.boar", [-1714986849] = "wolfmeat.burned",
			[-1691991080] = "wolfmeat.cooked", [179448791] = "wolfmeat.raw", [431617507] = "wolfmeat.spoiled",
			[688032252] = "metal.fragments", [-1059362949] = "metal.ore", [1265861812] = "metal.plate.torso",
			[374890416] = "metal.refined", [1567404401] = "metalblade", [-1057402571] = "metalpipe",
			[-758925787] = "mining.pumpjack", [-1411620422] = "mining.quarry", [88869913] = "fish.minnows",
			[-2094080303] = "smg.mp5", [843418712] = "mushroom", [-1569356508] = "weapon.mod.muzzleboost",
			[-1569280852] = "weapon.mod.muzzlebrake", [449769971] = "pistol.nailgun",
			[590532217] = "ammo.nailgun.nails", [3387378] = "note", [1767561705] = "burlap.trousers",
			[106433500] = "pants", [-1334615971] = "heavy.plate.pants", [-135651869] = "attire.hide.pants",
			[-1595790889] = "roadsign.kilt", [-459156023] = "pants.shorts", [106434956] = "paper",
			[-578028723] = "pickaxe", [-586116979] = "jar.pickle", [-1379225193] = "pistol.eoka",
			[-930579334] = "pistol.revolver", [548699316] = "pistol.semiauto", [142147109] = "planter.large",
			[148953073] = "planter.small", [102672084] = "attire.hide.poncho", [640562379] = "pookie.bear",
			[-1732316031] = "xmas.present.large", [-2130280721] = "xmas.present.medium",
			[-1725510067] = "xmas.present.small", [1974032895] = "propanetank", [-225085592] = "pumpkin",
			[509654999] = "clone.pumpkin", [466113771] = "seed.pumpkin", [2033918259] = "pistol.python",
			[2069925558] = "target.reactive", [-1026117678] = "box.repair.bench", [1987447227] = "research.table",
			[540154065] = "researchpaper", [1939428458] = "riflebody", [-288010497] = "roadsign.jacket",
			[-847065290] = "roadsigns", [3506021] = "rock", [649603450] = "rocket.launcher", [3506418] = "rope",
			[569935070] = "rug.bear", [113284] = "rug", [1916127949] = "water.salt", [-1775234707] = "salvaged.cleaver",
			[-388967316] = "salvaged.sword", [2007564590] = "santahat", [-1705696613] = "scarecrow",
			[670655301] = "hazmatsuit_scientist", [1148128486] = "hazmatsuit_scientist_peacekeeper",
			[-141135377] = "weapon.mod.small.scope", [109266897] = "scrap", [-527558546] = "searchlight",
			[-1745053053] = "rifle.semiauto", [1223860752] = "semibody", [-419069863] = "sewingkit",
			[-1617374968] = "sheetmetal", [2057749608] = "shelves", [24576628] = "shirt.collared",
			[-1659202509] = "shirt.tanktop", [2107229499] = "shoes.boots", [191795897] = "shotgun.double",
			[-1009492144] = "shotgun.pump", [2077983581] = "shotgun.waterpipe", [378365037] = "guntrap",
			[-529054135] = "shutter.metal.embrasure.a", [-529054134] = "shutter.metal.embrasure.b",
			[486166145] = "shutter.wood.a", [1628490888] = "sign.hanging.banner.large", [1498516223] = "sign.hanging",
			[-632459882] = "sign.hanging.ornate", [-626812403] = "sign.pictureframe.landscape",
			[385802761] = "sign.pictureframe.portrait", [2117976603] = "sign.pictureframe.tall",
			[1338515426] = "sign.pictureframe.xl", [-1455694274] = "sign.pictureframe.xxl",
			[1579245182] = "sign.pole.banner.large", [-587434450] = "sign.post.double",
			[-163742043] = "sign.post.single", [-1224714193] = "sign.post.town", [644359987] = "sign.post.town.roof",
			[-1962514734] = "sign.wooden.huge", [-705305612] = "sign.wooden.large", [-357728804] = "sign.wooden.medium",
			[-698499648] = "sign.wooden.small", [1213686767] = "weapon.mod.silencer",
			[386382445] = "weapon.mod.simplesight", [1859976884] = "skull_fire_pit", [960793436] = "skull.human",
			[1001265731] = "skull.wolf", [1253290621] = "sleepingbag", [470729623] = "small.oil.refinery",
			[1051155022] = "stash.small", [865679437] = "fish.troutsmall", [927253046] = "smallwaterbottle",
			[109552593] = "smg.2", [-2092529553] = "smgbody", [691633666] = "snowball", [-2055888649] = "snowman",
			[621575320] = "shotgun.spas12", [-2118132208] = "spear.stone", [-1127699509] = "spear.wooden",
			[-685265909] = "spikes.floor", [552706886] = "spinner.wheel", [1835797460] = "metalspring",
			[-892259869] = "sticks", [-1623330855] = "stocking.large", [-1616524891] = "stocking.small",
			[789892804] = "stone.pickaxe", [-1289478934] = "stonehatchet", [-892070738] = "stones",
			[-891243783] = "sulfur", [889398893] = "sulfur.ore", [-1625468793] = "supply.signal",
			[1293049486] = "surveycharge", [1369769822] = "fishtrap.small", [586484018] = "syringe.medical",
			[110115790] = "table", [1490499512] = "targeting.computer", [3552619] = "tarp", [1471284746] = "techparts",
			[456448245] = "smg.thompson", [110547964] = "torch", [1588977225] = "xmas.decoration.baubels",
			[918540912] = "xmas.decoration.candycanes", [-471874147] = "xmas.decoration.gingerbreadmen",
			[205978836] = "xmas.decoration.lights", [-1044400758] = "xmas.decoration.pinecone",
			[-2073307447] = "xmas.decoration.star", [435230680] = "xmas.decoration.tinsel", [-864578046] = "tshirt",
			[1660607208] = "tshirt.long", [260214178] = "tunalight", [-1847536522] = "vending.machine",
			[-496055048] = "wall.external.high.stone", [-1792066367] = "wall.external.high",
			[562888306] = "wall.frame.cell.gate", [-427925529] = "wall.frame.cell",
			[995306285] = "wall.frame.fence.gate", [-378017204] = "wall.frame.fence",
			[447918618] = "wall.frame.garagedoor", [313836902] = "wall.frame.netting",
			[1175970190] = "wall.frame.shopfront", [525244071] = "wall.frame.shopfront.metal",
			[-1021702157] = "wall.window.bars.metal", [-402507101] = "wall.window.bars.toptier",
			[-1556671423] = "wall.window.bars.wood", [61936445] = "wall.window.glass.reinforced", [112903447] = "water",
			[1817873886] = "water.catcher.large", [1824679850] = "water.catcher.small", [-1628526499] = "water.barrel",
			[547302405] = "waterjug", [1840561315] = "water.purifier", [-460592212] = "xmas.window.garland",
			[3655341] = "wood", [1554697726] = "wood.armor.jacket", [-1883959124] = "wood.armor.pants",
			[-481416622] = "workbench1", [-481416621] = "workbench2", [-481416620] = "workbench3",
			[-1151126752] = "xmas.lightstring", [-1926458555] = "xmas.tree"
		};

		#region Classes

		private class InfoResult
		{
			public string result;
			public string message = string.Empty;
			public bool maintenance = false;

			public Data data = null;

			public class Data
			{
				public int defaultBalance;
				public string link;
				public List<string> servers;
			}
		}

		private class MaintenanceResult
		{
			public string result;
			public string message = string.Empty;
			public bool maintenance = false;
		}

		private class WItem
		{
			#region Fields

			public bool IsValid;

			public string basketId;
			public string productId;
			public string Name;
			public int ItemID;
			public ItemDefinition itemDefinition;
			public int Amount;
			public List<object> commands = new();
			public string Command; // Поддержка команды как строки (для совместимости с ProstojRUST)
			public string ImageUrl;

			public ulong SteamID;

			public bool IsBlueprint;
			public bool IsCommand;
			public bool IsItem;
			public bool IsSet; // Флаг для наборов (bundles)

			#region Blocks

			public bool IsBlocked;

			public double LeftTime;

			#endregion

			#endregion

			#region Constructors

			public WItem(Dictionary<string, object> data)
			{
				try
				{
					if (data.TryGetValue("basketId", out var idBasket)) basketId = Convert.ToString(idBasket);

					if (data.TryGetValue("productId", out var idProduct)) productId = Convert.ToString(idProduct);

					if (data.TryGetValue("name", out var name)) Name = Convert.ToString(name);

					if (data.TryGetValue("type", out var type))
					{
						string typeStr = Convert.ToString(type);
						switch (typeStr)
						{
							case "bp":
								IsBlueprint = true;
								break;
							case "command":
								IsCommand = true;
								break;
							case "item":
								IsItem = true;
								break;
							case "set":
								// Наборы обрабатываются отдельно, не устанавливаем тип здесь
								// Но если есть поле "items", это набор
								if (data.TryGetValue("items", out var setItems) ||
								    (data.TryGetValue("data", out var setData) && setData is Dictionary<string, object> setDataDict && setDataDict.TryGetValue("items", out var _)))
								{
									// Это набор, устанавливаем флаг IsSet
									IsSet = true;
									// Не устанавливаем IsItem/IsCommand/IsBlueprint
									// Наборы обрабатываются отдельно в HandleBuyPopularItemResponse
								}
								break;
						}
					}

					if (data.TryGetValue("steamId", out var steamId))
						SteamID = Convert.ToUInt64(steamId);

					// Сначала пробуем найти itemId в корне данных (до проверки вложенной структуры)
					// Это важно, так как API может возвращать itemId в корне
					if (IsItem || IsBlueprint)
					{
						bool itemIdFound = false;
						// Пробуем itemId в корне данных
						if (data.TryGetValue("itemId", out var itemIdCamelCase))
						{
							if (itemIdCamelCase != null)
							{
								string itemIdStr = itemIdCamelCase.ToString().Trim();
								if (!string.IsNullOrEmpty(itemIdStr))
								{
									// Пробуем парсить как int (может быть отрицательным)
									if (int.TryParse(itemIdStr, out var itemIdFromRoot))
									{
										ItemID = itemIdFromRoot;
										itemIdFound = true;
									}
								}
							}
						}
						// Если не найден, пробуем item_id в корне
						if (!itemIdFound && data.TryGetValue("item_id", out var itemIdRoot))
						{
							if (itemIdRoot != null)
							{
								string itemIdStr = itemIdRoot.ToString().Trim();
								if (!string.IsNullOrEmpty(itemIdStr))
								{
									// Пробуем парсить как int (может быть отрицательным)
									if (int.TryParse(itemIdStr, out var itemIdFromRoot))
									{
										ItemID = itemIdFromRoot;
										itemIdFound = true;
									}
								}
							}
						}
					}

					if (data.TryGetValue("data", out var product))
						if (product is Dictionary<string, object> productData)
						{
							if (IsItem || IsBlueprint)
							{
								// Если itemId еще не найден, пробуем в productData
								if (ItemID == 0)
								{
									if (productData.TryGetValue("itemId", out var itemIdObj))
									{
										if (itemIdObj != null)
										{
											string itemIdStr = itemIdObj.ToString().Trim();
											if (!string.IsNullOrEmpty(itemIdStr))
											{
												if (int.TryParse(itemIdStr, out var itemId))
												{
													ItemID = itemId;
												}
											}
										}
									}
									if (ItemID == 0 && productData.TryGetValue("item_id", out var itemIdObj2))
									{
										// Пробуем item_id с подчеркиванием в productData
										if (itemIdObj2 != null)
										{
											string itemIdStr = itemIdObj2.ToString().Trim();
											if (!string.IsNullOrEmpty(itemIdStr))
											{
												if (int.TryParse(itemIdStr, out var itemId))
												{
													ItemID = itemId;
												}
											}
										}
									}
								}

								// Для наборов ItemID может быть 0, но если есть ImageUrl, товар все равно валиден
								// Проверку ItemID == 0 переносим ниже, после получения ImageUrl
							}

							if (IsCommand)
							{
								// Поддержка команды как строки (для совместимости с ProstojRUST)
								if (productData.TryGetValue("command", out var commandStr))
								{
									Command = Convert.ToString(commandStr);
									// Преобразуем строку команды в список для единообразной обработки
									if (!string.IsNullOrEmpty(Command))
									{
										commands = new List<object> { Command };
									}
								}
								// Поддержка команды как списка
								else if (productData.TryGetValue("commands", out var commandsObj))
								{
									commands = commandsObj as List<object>;
								}
							}
						}
						else
						{
							// Если productData нет, проверяем itemId в корне данных (если еще не найден)
							if ((IsItem || IsBlueprint) && ItemID == 0)
							{
								if (data.TryGetValue("itemId", out var itemIdRoot))
								{
									// Пробуем itemId в camelCase из корня
									if (itemIdRoot != null)
									{
										string itemIdStr = itemIdRoot.ToString().Trim();
										if (!string.IsNullOrEmpty(itemIdStr) && int.TryParse(itemIdStr, out var itemId))
										{
											ItemID = itemId;
										}
									}
								}
								if (ItemID == 0 && data.TryGetValue("item_id", out var itemIdRoot2))
								{
									// Пробуем item_id с подчеркиванием из корня
									if (itemIdRoot2 != null)
									{
										string itemIdStr = itemIdRoot2.ToString().Trim();
										if (!string.IsNullOrEmpty(itemIdStr) && int.TryParse(itemIdStr, out var itemId))
										{
											ItemID = itemId;
										}
									}
								}
							}
						}

						// Проверяем команду также в корне данных (для совместимости с ProstojRUST)
						if (IsCommand && string.IsNullOrEmpty(Command) && (commands == null || commands.Count == 0))
						{
							if (data.TryGetValue("command", out var commandRoot))
							{
								Command = Convert.ToString(commandRoot);
								if (!string.IsNullOrEmpty(Command))
								{
									commands = new List<object> { Command };
								}
							}
							else if (data.TryGetValue("commands", out var commandsRoot))
							{
								if (commandsRoot is List<object> commandsList)
								{
									commands = commandsList;
								}
							}
						}

						// Проверяем команду также в корне данных (для совместимости с ProstojRUST)
						if (IsCommand && string.IsNullOrEmpty(Command) && (commands == null || commands.Count == 0))
						{
							if (data.TryGetValue("command", out var commandRoot))
							{
								Command = Convert.ToString(commandRoot);
								if (!string.IsNullOrEmpty(Command))
								{
									commands = new List<object> { Command };
								}
							}
							else if (data.TryGetValue("commands", out var commandsRoot))
							{
								if (commandsRoot is List<object> commandsList)
								{
									commands = commandsList;
								}
							}

							// Если команды нет ни в виде строки, ни в виде списка, товар невалиден
							if ((commands == null || commands.Count == 0) && string.IsNullOrEmpty(Command))
								return;
						}

					if (data.TryGetValue("img", out var imgUrl)) ImageUrl = Convert.ToString(imgUrl);

					// Безопасное преобразование amount
					if (data.TryGetValue("amount", out var value))
					{
						if (value != null && int.TryParse(value.ToString(), out var amount))
						{
							Amount = amount;
						}
					}

					if (IsItem || IsBlueprint)
					{
						// Если ItemID == 0 и нет ImageUrl, товар невалиден (для наборов нужен ImageUrl)
						if (ItemID == 0 && string.IsNullOrEmpty(ImageUrl))
							return;

						// Пытаемся найти itemDefinition только если ItemID != 0
						if (ItemID != 0)
						{
							itemDefinition = ItemManager.FindItemDefinition(ItemID);
							if (itemDefinition == null && IDToShortName.TryGetValue(ItemID, out var shortname))
								itemDefinition = ItemManager.FindItemDefinition(shortname);
						}

						// Для предметов (IsItem) itemDefinition желателен, но если есть itemId, можно продолжить
						// Для наборов (sets) может быть ImageUrl без itemDefinition, но тогда IsItem должен быть false
						if (IsItem && itemDefinition == null)
						{
							// Если это предмет, но itemDefinition не найден
							// Если есть itemId, оставляем IsItem = true (возможно, itemDefinition будет найден позже)
							if (ItemID != 0)
							{
								// Оставляем IsItem = true, так как есть itemId
								// Товар будет обработан, даже если itemDefinition не найден
							}
							else if (string.IsNullOrEmpty(ImageUrl))
							{
								// Если нет ImageUrl и нет itemId и нет itemDefinition, товар невалиден
								return;
							}
							else
							{
								// Если есть ImageUrl, но нет itemId, возможно это набор
								IsItem = false;
							}
						}
						else if (IsBlueprint && itemDefinition == null && string.IsNullOrEmpty(ImageUrl))
						{
							// Для чертежей без itemDefinition и без ImageUrl товар невалиден
							return;
						}
					}

					// Для команд валидность определяется наличием команды (уже проверено выше)
					// Для других типов товаров (если прошли все проверки) устанавливаем IsValid
					IsValid = true;

					// Проверяем isBlocked и leftTime из API
					if (data.TryGetValue("isBlocked", out var isBlockedObj))
					{
						IsBlocked = Convert.ToBoolean(isBlockedObj);
					}
					if (data.TryGetValue("leftTime", out var leftTimeObj))
					{
						if (double.TryParse(leftTimeObj?.ToString(), out var leftTime))
						{
							LeftTime = leftTime;
						}
					}

					// Логирование данных от API
					Interface.Oxide.LogInfo($"[WItem] Created. basketId={basketId}, productId={productId}, itemId={ItemID}, name={Name}, isItem={IsItem}, isCommand={IsCommand}, isBlueprint={IsBlueprint}, isBlocked={IsBlocked}, leftTime={LeftTime}, isValid={IsValid}");

#if !CARBON
					if (Instance?.ImageLibrary?.IsLoaded == true)
#endif
					{
						// Проверяем ImageLibrary (изображение может быть уже загружено)
						if (Instance.HasImage($"IconGS.{basketId}"))
						{
							var probablyId = Instance.GetImage($"IconGS.{basketId}");
							if (!string.IsNullOrEmpty(probablyId) && probablyId != Instance.NoImageID &&
							    probablyId != Instance.LoadingImageID)
								ImageUrl = probablyId;
						}
						// Если изображения нет в ImageLibrary, но есть URL, добавляем в ImageLibrary
						// Кеширование в локальную папку будет происходить при отображении в UI
						// НО только если ImageUrl является валидным URL (начинается с http:// или https://)
						// Если ImageUrl является числом (item ID), не пытаемся загружать его как URL
						else if (!string.IsNullOrEmpty(ImageUrl) &&
						         (ImageUrl.StartsWith("http://", StringComparison.OrdinalIgnoreCase) ||
						          ImageUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase)))
						{
							Instance.AddImage(ImageUrl.Replace("https", "http"),
								$"IconGS.{basketId}");
						}
					}
				}
				catch (Exception e)
				{
					Interface.Oxide.LogError($"[GameStoresRUST] Error parsing WItem: {e.Message}");
					Interface.Oxide.LogError($"[GameStoresRUST] Data: {JsonConvert.SerializeObject(data)}");
					Interface.Oxide.LogError($"[GameStoresRUST] StackTrace: {e.StackTrace}");
				}
			}

			#endregion
		}

        #endregion

        #endregion

        #region API

        #region Create Payment

        private Regex _checkCreatePaymentMessage = new(@"/^([^'`string.Empty]{1,20})$");

        [ConsoleCommand("gs.createpayment")]
        private void CmdCreatePayment(ConsoleSystem.Arg args)
		{
            if (!Initialized) return;
			if (args.Player() != null) return;

			ulong steamId = args.GetUInt64(0, 0UL);
			int amount = args.GetInt(1, 0);
			string methodName = args.GetString(2, "Custom");

			if (steamId < 70000000000000000UL)
			{
				return;
			}

			if (amount < 1 || amount > 1000000)
			{
				return;
			}

			if (methodName != "Custom" && _checkCreatePaymentMessage.IsMatch(methodName))
			{
				return;
			}

			string serverIp = ConVar.Server.ip;
			int serverPort = ConVar.Server.port;
			var reqParams = new Dictionary<string, string>
			{
				// store_id не нужен, сервер определяется по IP и порту
				["server_ip"] = serverIp,
				["server_port"] = serverPort.ToString(),
				["steam_id"] = steamId.ToString(),
				["create_player"] = "true",
				["amount"] = amount.ToString(),
				["method_name"] = methodName
			};

            RequestToPay("integrations/payments/custom", reqParams, (code, response) =>
            {
                switch (code)
                {
                    case 400:
                        HandleCreatePaymentSuccess(steamId.ToString(), response);
                        break;
                    case 200:
                        HandleCreatePaymentSuccess(steamId.ToString(), response);
                        break;
                    default:
                        return;
                }
            });
        }

        #endregion

        #region Player Balance

        #region Change Player Balance

        private Regex _checkBalanceMessage = new(@"/^([^'`string.Empty]{1,64})$");

		private const string
			API_ChangePlayerBalance_Action_Plus = "plus",
			API_ChangePlayerBalance_Action_Minus = "minus";

		private void API_ChangePlayerBalance(ulong steamId, int amount, string type, string message = null,
			Action<bool, string> callback = null)
		{
			#region Amount Check

			if (amount is < 1 or > 1000000)
			{
				HandlePlayerBalanceError(callback, steamId, "wrong_amount", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: количество должно быть в диапазоне от 1 до 1000000");
				return;
			}

			#endregion

			#region Type Check

			int typeInt;
			switch (type)
			{
				case API_ChangePlayerBalance_Action_Plus:
					typeInt = 1;
					break;

				case API_ChangePlayerBalance_Action_Minus:
					typeInt = 2;
					break;

				default:
					HandlePlayerBalanceError(callback, steamId, "wrong_type", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: неверный тип. Возможные значения: plus, minus");
					return;
			}

			#endregion

			#region Message Check

			if (!string.IsNullOrEmpty(message) && _checkBalanceMessage.IsMatch(message))
			{
				HandlePlayerBalanceError(callback, steamId, "wrong_message", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: сообщение не может содержать специальные символы");
				return;
			}

			#endregion

			#region Request Parameters

			var reqParams = new Dictionary<string, string>
			{
				["steamId"]= steamId.ToString(),
				["type"] = typeInt == 1 ? API_ChangePlayerBalance_Action_Plus : API_ChangePlayerBalance_Action_Minus,
				["amount"] = amount.ToString()
			};

			if (!string.IsNullOrEmpty(message)) reqParams.Add("message", message);

			#endregion

			Request("players.item.balance.change", reqParams, (code, response) =>
			{
				switch (code)
				{
					case 400:
					case 200:
						HandlePlayerBalanceChangeSuccess(steamId, response, callback);
						break;

					default:
						HandlePlayerBalanceError(callback, steamId, "request_error", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}. Код: {code}, ответ от сервера: {response}");
						return;
				}
			});
		}

		#region Handlers

		private void HandlePlayerBalanceChangeSuccess(ulong steamId, string response, Action<bool, string> callback)
		{
			try
			{
				var json = JObject.Parse(response);
				var result = json["result"].ToString();

				switch (result)
				{
					case "success":
					{
						var balance = json["data"]?["balance"]?.ToString();
						if (balance != null && float.TryParse(balance, out var balanceFloat))
						{
							callback?.Invoke(true, Math.Round(balanceFloat).ToString());
							return;
						}

						HandlePlayerBalanceError(callback, steamId, "parse_error", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: Ошибка парсинга JSON");
						break;
					}

					case "fail" when json["playerNotFound"]?.ToString().ToLower() == "true":
						HandlePlayerBalanceError(callback, steamId, "player_not_found", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: Игрок не найден");
						break;

					default:
						HandlePlayerBalanceError(callback, steamId, "request_error", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}. Неизвестная ошибка при сериализации ответа: {response}");
						break;
				}
			}
			catch (JsonException ex)
			{
				HandlePlayerBalanceError(callback, steamId, "exception", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}: ошибка при сериализации ответа: {ex.Message}");
			}
		}

        private void HandleCreatePaymentSuccess(string steamId, string response)
        {
            try
            {
                var json = JObject.Parse(response);
                var result = json["result"].ToString();

				switch (result)
				{
					case "success":
						{

                            var playerBalance = json["data"]?["player_balance"]?.ToString();
							var storeBalance = json["data"]?["store_balance"]?.ToString();
							var paymentId = json["data"]?["payment_id"]?.ToString();

							break;
						}
					case "fail" when json["code"]?.ToString() == "102":
						break;
					default:
						break;
				}
			}
            catch (JsonException ex)
            {
            }
        }

        #endregion

        #endregion

        #region Get Player Balance

        private void API_GetPlayerBalance(ulong steamId,
			Action<bool, string> callback = null)
		{
			#region Request Parameters

			var reqParams = new Dictionary<string, string>
			{
				["steamId"] = steamId.ToString()
			};

			#endregion

			Request("players.item.balance", reqParams, (code, response) =>
			{
				switch (code)
				{
					case 400:
					case 200:
						HandlePlayerBalanceGetSuccess(steamId, response, callback);
						break;

					default:
						HandlePlayerBalanceError(callback, steamId, "request_error", $"Ошибка выполнения запроса для изменения баланса игрока {steamId}. Код: {code}, ответ от сервера: {response}");
						return;
				}
			});
		}

		#region Handlers

		private void HandlePlayerBalanceGetSuccess(ulong steamId, string response, Action<bool, string> callback)
		{
			try
			{
				var json = JObject.Parse(response);
				var result = json["result"].ToString();

				switch (result)
				{
					case "success":
					{
						var balance = json["data"]?["balance"]?.ToString();
						if (balance != null && float.TryParse(balance, out var balanceFloat))
						{
							callback?.Invoke(true, Math.Round(balanceFloat).ToString());
							return;
						}

						HandlePlayerBalanceError(callback, steamId, "parse_error", $"Ошибка выполнения запроса для получения баланса игрока {steamId}: Ошибка парсинга JSON");
						break;
					}

					case "fail" when json["playerNotFound"]?.ToString().ToLower() == "true":
						HandlePlayerBalanceError(callback, steamId, "player_not_found", $"Ошибка выполнения запроса для получения баланса игрока {steamId}: Игрок не найден");
						break;

					default:
						HandlePlayerBalanceError(callback, steamId, "request_error", $"Ошибка выполнения запроса для получения баланса игрока {steamId}. Неизвестная ошибка при сериализации ответа: {response}");
						break;
				}
			}
			catch (JsonException ex)
			{
				HandlePlayerBalanceError(callback, steamId, "exception", $"Ошибка выполнения запроса для получения баланса игрока {steamId}: ошибка при сериализации ответа: {ex.Message}");
			}
		}

		#endregion

		#endregion

		#region Handlers

		private void HandlePlayerBalanceError(Action<bool, string> callback, ulong steamId, string errorCode,
			string errorMessage)
		{
			callback?.Invoke(false, errorCode);
		}

		#endregion

		#endregion

		#endregion

		#region Queues

		#region Basket

		private class BasketRequest
		{
			public ulong SteamId;

			public BasePlayer Player;

			public int Page;
		}

		private LinkedList<BasketRequest> basketQueue = new();
		private int basketConcurrentRequestCount, basketMaxConcurrentRequests = 10;

		public void RequestBasket(BasePlayer player, int page = 0)
		{
			if (player == null || !player.IsConnected) return;

			// Защита от частых запросов - минимум 1 секунда между запросами
			double currentTime = CurrentTime();
			if (_lastBasketRequestTime.TryGetValue(player.userID, out var lastRequestTime))
			{
				double timeSinceLastRequest = currentTime - lastRequestTime;
				if (timeSinceLastRequest < 1.0)
				{
					LogAction(player, $"RequestBasket: Request ignored - too frequent. Time since last: {timeSinceLastRequest:F2}s", true);
					return;
				}
			}

			// Проверяем, не выполняется ли уже запрос
			if (_basketRequestInProgress.TryGetValue(player.userID, out var inProgress) && inProgress)
			{
				LogAction(player, "RequestBasket: Request ignored - already in progress", true);
				return;
			}

			// Проверяем, нет ли уже запроса в очереди
			if (!CanRequestBasket(player))
			{
				LogAction(player, "RequestBasket: Request ignored - already in queue", true);
				return;
			}

			_lastBasketRequestTime[player.userID] = currentTime;
			_basketRequestInProgress[player.userID] = true;

			var basketRequest = new BasketRequest
			{
				SteamId = player.userID,
				Player = player,
				Page = page
			};

			basketQueue.AddLast(basketRequest);

			LogAction(player, $"RequestBasket: Starting request. page={page}, queueSize={basketQueue.Count}", true);

			if (basketConcurrentRequestCount >= basketMaxConcurrentRequests)
			{
				HandleMaxConcurrentRequestsReached(player);
			}
			else
			{
				basketConcurrentRequestCount++;

				StartBasketOpeningProcess(basketRequest);
			}
		}

		private void StartBasketOpeningProcess(BasketRequest basketRequest)
		{
			var playerUserID = basketRequest.SteamId;

			Request("baskets.bySteamId", new Dictionary<string, string>
			{
				{"steamId", playerUserID.ToString()}
			}, (code, response) =>
			{
				DequeueBasketRequest(playerUserID);
				_basketRequestInProgress[playerUserID] = false;

				switch (code)
				{
					default:
						HandleBasketUnavailable(basketRequest.Player);
						break;
					case 400:
					case 200:
						HandleBasketResponse(basketRequest.Player, response, basketRequest.Page);
						break;
				}
			}, basketRequest.Player);
		}

		private void TriggerNextBasketRequest()
		{
			if (basketQueue.Count <= 0) return;

			var nextBasketRequest = basketQueue.First?.Value;
			if (nextBasketRequest == null) return;

			StartBasketOpeningProcess(nextBasketRequest);
		}

		private bool CanRequestBasket(BasePlayer player)
		{
			return basketQueue.All(x => x.SteamId != player.userID);
		}

		private bool TryGetRequestBasket(BasePlayer player, out BasketRequest basketRequest)
		{
			foreach (var request in basketQueue)
				if (request.SteamId == player.userID)
				{
					basketRequest = request;
					return true;
				}

			basketRequest = null;
			return false;
		}

		private void DequeueBasketRequest(ulong steamID)
		{
			foreach (var basketRequest in basketQueue)
			{
				if (basketRequest.SteamId != steamID)
					continue;

				basketQueue.Remove(basketRequest);

				basketConcurrentRequestCount--;
				break;
			}

			TriggerNextBasketRequest();
		}

		#endregion

		#region Get Item

		private class GetItemRequest
		{
			public BasePlayer Player;

			public string RequestId;

			public bool Instant;

			public int BasketId;

			public int Index;
		}

		private LinkedList<GetItemRequest> getItemQueue = new();

		private int
			getItemConcurrentRequestCount,
			getItemMaxConcurrentRequests = 10;

		private void RequestGetItem(BasePlayer player, int basketID, int index = 0)
		{
			if (!CanRequestGetItem(basketID)) return;

			var getItemRequest = new GetItemRequest
			{
				Player = player,
				Index = index,
				RequestId = CuiHelper.GetGuid(),
				BasketId = basketID
			};

			getItemQueue.AddLast(getItemRequest);

			if (getItemConcurrentRequestCount >= getItemMaxConcurrentRequests)
			{
				HandleMaxConcurrentRequestsReached(player);
			}
			else
			{
				getItemConcurrentRequestCount++;

				StartGetItemProcess(getItemRequest);
			}
		}

		private void RequestGetItem(int basketID, bool instant = false)
		{
			if (!CanRequestGetItem(basketID))
				return;

			var getItemRequest = new GetItemRequest
			{
				RequestId = CuiHelper.GetGuid(),
				Instant = instant,
				BasketId = basketID
			};

			getItemQueue.AddLast(getItemRequest);

			if (getItemConcurrentRequestCount < getItemMaxConcurrentRequests)
			{
				getItemConcurrentRequestCount++;

				StartGetItemProcess(getItemRequest);
				return;
			}

			HandleMaxConcurrentRequestsReached(basketID);
		}

		private void StartGetItemProcess(GetItemRequest getItemRequest)
		{
			Request("baskets.item", new Dictionary<string, string>
			{
				{"basketId", getItemRequest.BasketId.ToString()}
			}, (code, response) =>
			{
				DequeueGetItemRequest(getItemRequest.RequestId);

				if (getItemRequest.Instant)
					HandleTakeInstantResponse(code, response, getItemRequest.BasketId);
				else
					HandleTakeResponse(getItemRequest.Player, getItemRequest.Index, getItemRequest.BasketId, code,
						response);
			}, getItemRequest.Player);
		}

		private void TriggerNextGetItemRequest()
		{
			if (getItemQueue.Count <= 0) return;

			var nextGetItemqRequest = getItemQueue.First?.Value;
			if (nextGetItemqRequest == null) return;

			StartGetItemProcess(nextGetItemqRequest);
		}

		private bool CanRequestGetItem(int basketID)
		{
			return getItemQueue.All(x => x.BasketId != basketID);
		}

		private bool TryGetRequestItem(int basketID, out GetItemRequest getItemRequest)
		{
			foreach (var request in getItemQueue)
				if (request.BasketId == basketID)
				{
					getItemRequest = request;
					return true;
				}

			getItemRequest = null;
			return false;
		}

		private void DequeueGetItemRequest(string requestId)
		{
			foreach (var itemRequest in getItemQueue)
			{
				if (itemRequest.RequestId != requestId) continue;

				getItemQueue.Remove(itemRequest);

				getItemConcurrentRequestCount--;
				break;
			}

			TriggerNextGetItemRequest();
		}

		#endregion

		#region Handlers

		private void HandleMaxConcurrentRequestsReached(BasePlayer player)
		{
			ShowNotify(player, Msg(player, MsgManyRequests), true);
		}

		private void HandleMaxConcurrentRequestsReached(int basketID)
		{
		}

		#endregion

		#endregion

		#region Stable Connection

		private Timer RestartTimer;

		private void SetReserveApiLink()
		{
			if (BestApiLink.Contains(MainApiLink))
			{
				BestApiLink = CFApiLink;

				if (!Initialized) FetchShopUrl();

				timer.In(5 * 60, TryChangeLink);
			}
			else
			{
				RestartTimer = timer.In(5 * 60, () =>
				{
					Interface.Oxide.ReloadPlugin(Title);
				});
			}
		}

		private void TryChangeLink()
		{
			var newLink = CFApiLink;
			if (BestApiLink.Contains(CFApiLink))
				newLink = MainApiLink;

			CheckRequest(newLink, "store.pluginInfo", (code, response) =>
			{
				switch (code)
				{
					case 200:
					{
						if (response.Length < 1)
							return;

						var firstInfo =
							JsonConvert.DeserializeObject<Dictionary<string, object>>(response,
								new KeyValuesConverter());
						if (firstInfo == null || !firstInfo.ContainsKey("data"))
						{
							initialization = false;
						}
						else
						{
							if (RestartTimer != null)
							{
								RestartTimer.Destroy();
								RestartTimer = null;
							}

							BestApiLink = newLink;

							if (!Initialized) FetchShopUrl();

							if (BestApiLink.Contains(CFApiLink))
								timer.In(10 * 60, TryChangeLink);
						}

						break;
					}
				}
			});
		}

		#endregion

		#region Testing Functions

#if TESTING
		private static void SayDebug(string message)
		{
			Debug.Log($"[GameStores] {message}");
		}
#endif

		#endregion
	}
}
