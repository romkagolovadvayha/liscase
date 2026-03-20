# Структура RustDemoPro.dll (вывод InspectDll)

Сборка: **RustDemoPro, Version=0.2.1.0**. Код **обфусцирован** (типы в пространстве имён `A` с короткими именами), но по сигнатурам и полям видно следующее.

---

## Точка входа (лоадер)

- **RustDemoPro.HarmonyMod.Loader.RustDemoProLoader**
  - `OnLoaded(OnHarmonyModLoadedArgs args)`
  - `OnUnloaded(OnHarmonyModUnloadedArgs args)`

То есть мод загружается как обычный HarmonyMod через этот лоадер.

---

## Harmony-патчи (классы в пространстве A)

| Класс | Метод | Цель |
|-------|--------|------|
| **A.t** | `Postfix(BaseCombatEntity __instance, HitInfo info)` | Смерть сущности (килл/смерть игрока) |
| **A.U** | `Postfix(BaseCombatEntity __instance, HitInfo info)` | Ещё один хук на смерть |
| **A.r** | `Postfix(BasePlayer __instance)` | Что-то по игроку |
| **A.S** | `Postfix(BasePlayer __instance)` | То же |
| **A.s** | `Prefix(BasePlayer __instance, BaseEntity/RPCMessage msg)` | RPC-сообщение от клиента (например, старт/стоп записи) |
| **A.T** | `Prefix(Facepunch.RCon/LogType type, System.Object obj)` | Логи RCon |

То есть киллы обрабатываются через патчи на `BaseCombatEntity` + `HitInfo`, а взаимодействие с записью демо — через RPC от `BasePlayer`.

---

## Важные типы данных (по полям)

### Окно захвата «до/после»

- **A.k** (report context):
  - `captureWindowBeforeMinutes`, `captureWindowAfterMinutes`
  - `captureWindowStartUtc`, `captureWindowEndUtc`
  - `reportId`, `reporterUserId`, `reportedUserId`, `reason`, `reportedAtUtc` и т.д.

То есть в моде явно есть окно захвата в минутах «до» и «после» (вероятно, для репортов/хайлайтов).

### Событие килла

- **A.J**:
  - `serverSeconds`, `serverTimeLocal`, `chunkOffsetSeconds`, `type`
  - `attackerUserId`, `attackerName`, `targetUserId`, `targetName`
  - `weaponPrefab`, `weaponShortName`, `ammoPrefab`, `hitArea`, `distance`, `damageTotal`, `info`

Похоже на одно событие убийства/смерти с привязкой к времени сервера и чанку.

### Метаданные чанка демо

- **A.i**:
  - `demoPath`, `demoFileName`, `chunkReason`
  - `startedUtc`, `endedUtc`, `startedLocal`, `endedLocal`
  - `startedServerSeconds`, `endedServerSeconds`, `durationSeconds`, `chunkMinutes`
  - `eventCount`, `droppedEventCount`, `reportContext`
  - `userId`, `steamId`, `playerName`, `plugin`, `version`, `serverIdentity`, `map` и т.д.

То есть для каждого куска демо хранится путь к файлу, временные границы и контекст (в т.ч. репорт).

### Загрузка на сервер

- **A.H** (bundle):
  - `uploadKey`, `demoFile`, `startedUtc`, `endedUtc`, `totalBytes`

- **A.h** (запрос/пакет загрузки):
  - `uploadKey`, `serverId`, `steamId`, `archiveName`, `compressed`
  - `bundles` (список A.H)
  - `reportContext` (A.k), `reportMarkerSeconds`

То есть на сервер уходят «пачки» с файлом демо и временными метками, привязанные к контексту репорта и окну до/после.

---

## Вывод

- Файл **RustDemoPro.dll в репозитории есть** (например, `plugins/RustDemoPro.dll`). Раньше поиск по репо его не показывал — мог быть другой путь или кэш.
- Мод **реализует окно захвата в минутах** (`captureWindowBeforeMinutes`, `captureWindowAfterMinutes`) и привязывает к нему чанки демо и загрузку (A.H, A.h, A.i, A.k).
- Киллы обрабатываются в патчах **A.t** и **A.U** (`BaseCombatEntity` + `HitInfo`); взаимодействие с записью — через **A.s** (RPC от `BasePlayer`).
- Код обфусцирован, поэтому **посмотреть логику «как именно режут 15 сек до/после»** удобнее в **dnSpy**: открыть `RustDemoPro.dll`, найти по строкам `captureWindowBeforeMinutes`, `captureWindowAfterMinutes`, `BaseCombatEntity`, `HitInfo`, классы `A.t`, `A.U`, `A.s` и смотреть уже декомпилированный C#.

Если нужно, можно отдельно выписать, какие именно методы в A.m / A.N / A.O отвечают за формирование чанков и вызов загрузки (по следующему запуску InspectDll или по dnSpy).
