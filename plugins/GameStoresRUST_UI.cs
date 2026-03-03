// CUI методы для GameStoresRUST плагина
// Этот файл содержит все методы, связанные с созданием пользовательского интерфейса

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

	if (first)
		CreateStoreExitButtonUI(player, ref container);

	CuiHelper.AddUi(player, container);

	if (first)
		RequestBasket(player, page);
	else
		ShowBasketItemsUI(player, page);
}

private void ShowNotify(BasePlayer player, string text, float destroyTime = 0f)
{
	CuiHelper.AddUi(player, new CuiElementContainer
	{
		{
			new CuiLabel
			{
				RectTransform =
				{
					AnchorMin = "0 0.80", AnchorMax = "1 0.90", OffsetMax = "0 0"
				},
				Text =
				{
					FadeIn = 1f, Text = text, Align = TextAnchor.UpperCenter,
					Font = "robotocondensed-regular.ttf",
					FontSize = 16
				}
			},
			StoreLayer, StoreLayer + ".Notify", StoreLayer + ".Notify"
		}
	});

	if (destroyTime > 0f)
		timer.In(destroyTime, () => CuiHelper.DestroyUi(player, StoreLayer + ".Notify"));
}

private void ShowHelpUI(BasePlayer player)
{
	CuiHelper.DestroyUi(player, StoreLayer);

	var container = new CuiElementContainer();

	container.Add(new CuiPanel
	{
		CursorEnabled = true,
		RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
		Image = {Color = "0 0 0 0.9", Material = "assets/content/ui/uibackgroundblur.mat"}
	}, "Overlay", HelpLayer, HelpLayer);

	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.6", AnchorMax = "1 0.78", OffsetMax = "0 0"},
		Text =
		{
			Text = Msg(player, MsgUserManual), Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-regular.ttf", FontSize = 34
		}
	}, HelpLayer);

	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.3", AnchorMax = "1 0.72", OffsetMax = "0 0"},
		Text =
		{
			Text = Msg(player, MsgUserManualDescription),
			Font = "robotocondensed-regular.ttf", FontSize = 22, Align = TextAnchor.MiddleCenter
		}
	}, HelpLayer);

	var addText = StartBalance > 0 ? Msg(player, MsgUserManualBalance, StartBalance) : string.Empty;
	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.1", AnchorMax = "1 0.48", OffsetMax = "0 0"},
		Text =
		{
			Text = $"{addText}\n{ShopURL.ToUpper()}", Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-regular.ttf", FontSize = 32
		}
	}, HelpLayer);
	container.Add(new CuiButton
	{
		RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
		Button = {Color = "0 0 0 0", Close = HelpLayer, Command = "chat.say /store"},
		Text = {Text = string.Empty}
	}, HelpLayer);

	CuiHelper.AddUi(player, container);
}

#region UI.Components

private int basketItemsPerPage => _config.UI.ItemOnString * _config.UI.StringAmount;

private void ShowBasketItemsUI(BasePlayer player, int page)
{
	// Определяем активную вкладку (по умолчанию "basket")
	if (!_playerActiveTab.TryGetValue(player.userID, out var activeTab))
		activeTab = "basket";

	var secondContainer = new CuiElementContainer();

	// Добавляем табы
	// Вкладка "Корзина"
	var isBasketActive = activeTab == "basket";
	secondContainer.Add(new CuiButton
	{
		RectTransform = {AnchorMin = "0.3 0.88", AnchorMax = "0.5 0.98", OffsetMax = "0 0"},
		Button = 
		{
			Color = isBasketActive ? "0.2 0.4 0.8 0.9" : "0.3 0.3 0.3 0.7",
			Command = "UI_GameStoresRUST tab basket"
		},
		Text = 
		{
			Text = Msg(player, MsgBasket),
			Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-bold.ttf",
			FontSize = 28,
			Color = isBasketActive ? "1 1 1 1" : "0.8 0.8 0.8 0.8"
		}
	}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.TabBasket");

	// Вкладка "Помощь"
	var isHelpActive = activeTab == "help";
	secondContainer.Add(new CuiButton
	{
		RectTransform = {AnchorMin = "0.5 0.88", AnchorMax = "0.7 0.98", OffsetMax = "0 0"},
		Button = 
		{
			Color = isHelpActive ? "0.2 0.4 0.8 0.9" : "0.3 0.3 0.3 0.7",
			Command = "UI_GameStoresRUST tab help"
		},
		Text = 
		{
			Text = Msg(player, MsgHelp),
			Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-bold.ttf",
			FontSize = 28,
			Color = isHelpActive ? "1 1 1 1" : "0.8 0.8 0.8 0.8"
		}
	}, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel.TabHelp");

	// Показываем содержимое в зависимости от активной вкладки
	if (activeTab == "help")
	{
		ShowHelpContent(player, ref secondContainer);
		CuiHelper.AddUi(player, secondContainer);
		return;
	}

	// Показываем корзину
	if (!_playerBaskets.TryGetValue(player.userID, out var playerBasket))
		return;

	var wItems = playerBasket.GetItems(page, basketItemsPerPage);

	// Номер страницы
	secondContainer.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0", AnchorMax = "1 0.14", OffsetMax = "0 0"},
		Text = {Text = (page + 1).ToString(), Align = TextAnchor.MiddleCenter, FontSize = 34}
	}, StoreLayer + ".BlockPanel");

	secondContainer.Add(new CuiButton
	{
		RectTransform =
			{AnchorMin = "0.4 0.14", AnchorMax = "0.4 0.14", OffsetMin = "-40 -125", OffsetMax = "125 40"},
		Button = {Color = "0 0 0 0", Command = page > 0 ? $"UI_GameStoresRUST page {page - 1}" : string.Empty},
		Text =
		{
			Text = "<", Color = page > 0 ? "1 1 1 1" : "1 1 1 0.2", Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-regular.ttf", FontSize = 80
		}
	}, StoreLayer + ".BlockPanel");

	secondContainer.Add(new CuiButton
	{
		RectTransform =
			{AnchorMin = "0.6 0.14", AnchorMax = "0.6 0.14", OffsetMin = "-125 -125", OffsetMax = "40 40"},
		Button =
		{
			Color = "0 0 0 0",
			Command = (page + 1) * basketItemsPerPage < playerBasket.GetCount()
				? $"UI_GameStoresRUST page {page + 1}"
				: string.Empty
		},
		Text =
		{
			Text = ">",
			Color = (page + 1) * basketItemsPerPage < playerBasket.GetCount() ? "1 1 1 1" : "1 1 1 0.2",
			Align = TextAnchor.MiddleCenter, Font = "robotocondensed-regular.ttf", FontSize = 80
		}
	}, StoreLayer + ".BlockPanel");

	for (var i = 0; i < basketItemsPerPage; i++)
	{
		UI_RecountPosition(out var xSwitch, out var ySwitch, i, basketItemsPerPage);

		secondContainer.Add(new CuiButton
			{
				RectTransform =
				{
					AnchorMin = "0.495 0.65",
					AnchorMax = "0.495 0.65",
					OffsetMin = $"{xSwitch} {ySwitch - _config.UI.ItemSide}",
					OffsetMax = $"{xSwitch + _config.UI.ItemSide} {ySwitch}"
				},
				Button = {Color = _config.UI.ItemBackgroundColor.Get, Command = string.Empty},
				Text = {Text = string.Empty}
			}, StoreLayer + ".BlockPanel", StoreLayer + $".BlockPanel.{i}");
	}

	for (var index = 0; index < wItems.Count; index++)
	{
		var item = wItems[index];

		ShowBasketItemUI(player, item, ref secondContainer, index);
	}

	CuiHelper.AddUi(player, secondContainer);
	CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Text");
}

private void ShowHelpContent(BasePlayer player, ref CuiElementContainer container)
{
	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.6", AnchorMax = "1 0.78", OffsetMax = "0 0"},
		Text =
		{
			Text = Msg(player, MsgUserManual), Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-regular.ttf", FontSize = 34
		}
	}, StoreLayer + ".BlockPanel");

	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.3", AnchorMax = "1 0.72", OffsetMax = "0 0"},
		Text =
		{
			Text = Msg(player, MsgUserManualDescription),
			Font = "robotocondensed-regular.ttf", FontSize = 22, Align = TextAnchor.MiddleCenter
		}
	}, StoreLayer + ".BlockPanel");

	var addText = StartBalance > 0 ? Msg(player, MsgUserManualBalance, StartBalance) : string.Empty;
	container.Add(new CuiLabel
	{
		RectTransform = {AnchorMin = "0 0.1", AnchorMax = "1 0.48", OffsetMax = "0 0"},
		Text =
		{
			Text = $"{addText}\n{ShopURL.ToUpper()}", Align = TextAnchor.MiddleCenter,
			Font = "robotocondensed-regular.ttf", FontSize = 32
		}
	}, StoreLayer + ".BlockPanel");
}

private void ShowBasketItemUI(BasePlayer player, WItem item, ref CuiElementContainer secondContainer, int index)
{
	if (item.IsBlueprint) ItemBlueprintImageUI(ref secondContainer, index);

	if (_config.Plugin.LoadSpriteImages && item.IsValid && item.itemDefinition != null)
	{
		secondContainer.Add(new CuiElement
		{
			Parent = StoreLayer + $".BlockPanel.{index}",
			Components =
			{
				new CuiImageComponent {ItemId = item.itemDefinition.itemid},
				new CuiRectTransformComponent
					{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5"}
			}
		});
	}
	else
	{
		if (!item.ImageUrl.Contains("http"))
			secondContainer.Add(new CuiElement
			{
				Parent = StoreLayer + $".BlockPanel.{index}",
				Components =
				{
					new CuiRawImageComponent {Png = item.ImageUrl},
					new CuiRectTransformComponent
						{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5"}
				}
			});
		else
			secondContainer.Add(new CuiElement
			{
				Parent = StoreLayer + $".BlockPanel.{index}",
				Components =
				{
					new CuiRawImageComponent {Url = item.ImageUrl},
					new CuiRectTransformComponent
						{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5"}
				}
			});
	}

	if (item.Amount > 1)
		secondContainer.Add(new CuiLabel
			{
				RectTransform =
					{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 5", OffsetMax = "-5 0"},
				Text =
				{
					Text = "x" + item.Amount, Align = TextAnchor.LowerRight,
					Font = "robotocondensed-regular.ttf", FontSize = 24
				}
			}, StoreLayer + $".BlockPanel.{index}");

	secondContainer.Add(new CuiButton
		{
			RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
			Button =
			{
				Color = "0 0 0 0", Close = StoreLayer + $".BlockPanel.{index}.Open",
				Command =
					$"UI_GameStoresRUST take {index} {item.basketId}"
			},
			Text = {Text = string.Empty}
		}, StoreLayer + $".BlockPanel.{index}", StoreLayer + $".BlockPanel.{index}.Open");

	if (_config.UI.TextShow)
		secondContainer.Add(new CuiLabel
			{
				RectTransform =
					{AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "0 -22.5", OffsetMax = "0 0"},
				Text =
				{
					Text = item.Name, Align = TextAnchor.MiddleCenter,
					Font = "robotocondensed-regular.ttf", FontSize = 16, Color = "1 1 1 0.8"
				}
			}, StoreLayer + $".BlockPanel.{index}");

	if (!CanRequestGetItem(Convert.ToInt32(item.basketId)))
	{
		ShowNotify(player, Msg(player, MsgTakeRequestProcessing));

		secondContainer.Add(new CuiButton
		{
			RectTransform = {AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0"},
			Button =
			{
				FadeIn = 1f, Color = "1 1 1 0.2",
				Close = StoreLayer + ".BlockPanel." + index + ".Open",
				Command = $"UI_GameStoresRUST take {index} {item.basketId}"
			},
			Text =
			{
				Text = Msg(player, MsgTakeWait), Align = TextAnchor.MiddleCenter,
				Font = "robotocondensed-bold.ttf", Color = "1 1 1 0.4", FontSize = 24
			}
		}, StoreLayer + ".BlockPanel." + index, StoreLayer + ".BlockPanel." + index + ".Open");
	}
}

private void ItemBlueprintImageUI(ref CuiElementContainer secondContainer, int index)
{
	if (_config.Plugin.LoadSpriteImages)
	{
		secondContainer.Add(new CuiElement
		{
			Parent = StoreLayer + $".BlockPanel.{index}",
			Components =
			{
				new CuiImageComponent {ItemId = ItemManager.blueprintBaseDef.itemid},
				new CuiRectTransformComponent
					{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5"}
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
					{AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5"}
			}
		});
	}
}

private void CreateStoreExitButtonUI(BasePlayer player, ref CuiElementContainer container)
{
	container.AddRange(_config.UI.StoreExitButton.Get(Msg(player, MsgExit), "closemenu", StoreLayer,
		close: StoreLayer));
}

private void CreateStoreUiContainer(ref CuiElementContainer container, BasePlayer player)
{
	container.Add(_config.UI.Background.Get(StoreLayer, StoreLayer, true));

	container.AddRange(_config.UI.BasketTitle.Get(Msg(player, MsgBasket), string.Empty, StoreLayer));

	container.Add(_config.UI.BasketDescription.GetText(Msg(player, MsgBasketDescription), StoreLayer));

	// Кнопка помощи убрана - теперь используется вкладка
}

private void CreateBlockPanel(ref CuiElementContainer container, BasePlayer player)
{
	container.Add(_config.UI.BlockPanel.GetImage(StoreLayer, StoreLayer + ".BlockPanel",
		StoreLayer + ".BlockPanel"));

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
		Button = {Color = "0 0 0 0", Command = "UI_GameStoresRUST open"},
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

#endregion

#region UI.Helpers

private void UI_RecountPosition(out double xSwitch, out double ySwitch, int count, int max)
{
	var stringAmount = Math.Ceiling((double) max / _config.UI.ItemOnString);
	var currentString = Math.Floor((double) count / _config.UI.ItemOnString);
	var currentPosition = count % _config.UI.ItemOnString;


	var topYPosition = 0 + (float) stringAmount / 2 * _config.UI.ItemSide +
	                   ((float) stringAmount / 2 - 1) * _config.UI.ItemMargin;
	var topXPosition = 0 - (float) _config.UI.ItemOnString / 2 * _config.UI.ItemSide -
	                   ((float) _config.UI.ItemOnString / 2 - 1) * _config.UI.ItemMargin;

	var curYPosition = topYPosition - currentString * _config.UI.ItemSide - currentString *
		(_config.UI.ItemMargin + (_config.UI.TextShow ? 20 : 0));
	var curXPosition = topXPosition + currentPosition * _config.UI.ItemSide +
	                   currentPosition * _config.UI.ItemMargin;

	xSwitch = curXPosition;
	ySwitch = curYPosition;
}

private static void UpdateUI(BasePlayer player, Action<CuiElementContainer> callback)
{
	if (player == null) return;

	var container = new CuiElementContainer();
	callback(container);
	CuiHelper.AddUi(player, container);
}

#endregion

#endregion

