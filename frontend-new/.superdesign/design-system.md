# PROSTOJ Homepage — Existing Web Design System

## Source of truth

This document extends the existing `prostoj-frontend` web system. Existing CSS variables and components are authoritative. Do not reinterpret the brand as a generic gaming UI and do not introduce an independent visual language.

Primary sources:

- `C:/xampp/htdocs/prostoj-frontend/src/styles/design-system.scss`
- `C:/xampp/htdocs/prostoj-frontend/src/styles/header.scss`
- `C:/xampp/htdocs/prostoj-frontend/src/styles/products.scss`
- `C:/xampp/htdocs/prostoj-frontend/src/components/forms/Button.tsx`

The page must use semantic variables so every existing PROSTOJ theme remains compatible. The default visual preview uses the `original` theme below.

## Existing original-theme tokens

```css
:root {
  --primary-colors-main: #eb0c35;
  --primary-colors-secondary: #ff6134;
  --primary-colors-secondary-opacity: rgba(255, 97, 52, 0.15);
  --primary-colors-white: #ffffff;
  --primary-colors-main-opacity: rgba(235, 12, 53, 0.4);

  --background-main: #080224;
  --background-secondary: #19102d;
  --background-teritiary: #2e1a3b;
  --background-secondary-dark: #100b13;
  --background-hover: #342341;

  --text-main: #ece4f3;
  --text-primary: var(--text-main);
  --text-secondary: #8f8f8f;
  --text-teritiary: #b5b5b5;
  --text-disabled: rgba(236, 228, 243, 0.2);

  --icon-main: #564a66;
  --icon-hover: #7f718c;
  --icon-mini: #ff6134;
  --icon-in-button: #ece4f3;
  --icon-social-main: #a298ae;

  --border-color-default: #3e3249;
  --border-color-hover: #7f718c;
  --border-color-active: #ece4f3;

  --link-color-default: #ff4814;
  --link-color-hover: #ff7834;
  --link-color-disabled: #67504a;

  --base-linear-gradiend: linear-gradient(90deg, #eb0c35 50%, #ff6134 100%);
  --button-primary-image-hover: linear-gradient(90deg, #eb0c35 0%, #ff6134 50.04%, #ffb834 100%);

  --system-colors-success-color: #009136;
  --system-colors-gold: #f8b34d;
  --system-colors-silver: #b4b4b4;
  --system-colors-bronze: #af7355;
  --online: #4bcc18;
  --progress-step-color: #ff7d58;

  --card-radius: 8px;
  --button-radius: 6px;
  --block-radius: 16px;
  --avatar-radius: 6px;
  --status-radius: 20px;
  --shadow-card: 4px 4px 4px rgba(0, 0, 0, 0.25);

  --font-main: 'Roboto', sans-serif;
  --motion-duration-fast: 150ms;
  --motion-duration-normal: 250ms;
  --motion-duration-slow: 350ms;
  --motion-ease-out: cubic-bezier(0.4, 0, 0.2, 1);
  --motion-ease-in-out: cubic-bezier(0.4, 0, 0.6, 1);
}
```

## Existing visual grammar

- Page background is `--background-main`; header, footer, cards and primary containers use `--background-secondary`.
- Raised or selected areas use `--background-teritiary`; hover uses `--background-hover`.
- Borders are 1px `--border-color-default`, changing to `--border-color-hover` on hover.
- Cards use 8px radius and `--shadow-card`. Large grouped blocks may use 16px radius.
- Main text is `--text-main`; secondary metadata is `--text-secondary`; supportive text is `--text-teritiary`.
- Primary actions use the existing red-to-orange `--base-linear-gradiend`; hover may use `--button-primary-image-hover`.
- Secondary buttons are transparent or `--background-secondary`, with a 1px border. Tertiary controls use `--background-teritiary`.
- Active navigation uses orange text and `--primary-colors-secondary-opacity` background.
- Social buttons are 30–32px circles with a border. Their brand colors appear only on hover.
- Product cards use a subtle brand-tinted image well and an optional blurred brand gradient only on hover. They are not flat black tiles.
- Existing micro-motion is restrained: 150–350ms, slight scale around 1.05–1.1, border/colour transition, soft glow only for brand/favourite/discount actions.
- Default web typography uses Roboto. Do not use Archivo, Space Mono, Inter, condensed display fonts, brutalism or terminal typography.

## Existing component measurements

- Desktop header: 64px height, 24px horizontal padding, 24–32px major gaps.
- Header nav items: 14px / 500, 8px gap, compact 8–12px internal padding, 6px radius.
- Buttons: small 32px, medium 40px, large 48px; 6px radius; 14px / 500–600.
- Social buttons: 30px in header, 32px in footer.
- Standard body text: 14px; secondary/meta: 12–13px; section headings: 16–20px / 600.
- Standard card padding: 16px; dense dashboard card padding may be 12px but must preserve the same border/radius/surface rules.
- Existing desktop layout uses 20px gaps; the no-scroll dashboard may use a compact 12px gap as a documented density variant.

## Homepage product context

PROSTOJ is a Rust server community and store. The desktop homepage behaves like an app and should fit a 1440×900 viewport without body scrolling. Internal lists may scroll. Below 1024px the normal responsive page may scroll vertically.

Shared header:

- Existing PROSTOJ logo asset, not a fabricated text/terminal logo.
- Menu: Новости, Календарь вайпов, Поддержка, Статистика, Кланы, Турниры.
- Existing circular Telegram, Discord, VK controls.
- Existing flag-based RU/EN switcher using `/flags/RU.svg` and `/flags/GB.svg`.
- Guest: existing `Button` primary variant with Steam icon and “Войти через Steam”.
- Authenticated: existing avatar/profile and segmented balance control patterns.

Guest modules:

- Five news: one visually stronger item and four compact rows.
- Five live servers with online/max and wipe status.
- Exactly six compact store products without filters.
- Compact wipe calendar.
- Small footer.

Authenticated additions:

- Mini map, squad and chat are added to the same dashboard.
- Public modules remain present but become compact rails/rows.
- Profile shows avatar, username `A_tonna3kg`, server `#5 X10`, balance `1 240 ₽`.

## New semantic extension tokens

These tokens fill gaps for the compact homepage. Every value is derived from an existing semantic token so theme switching continues to work.

```css
:root {
  --dashboard-gap: 12px;
  --dashboard-pad: 12px;
  --dashboard-panel-bg: var(--background-secondary);
  --dashboard-panel-raised: var(--background-teritiary);
  --dashboard-panel-hover: var(--background-hover);
  --dashboard-panel-border: var(--border-color-default);
  --dashboard-panel-border-hover: var(--border-color-hover);
  --dashboard-panel-radius: var(--card-radius);
  --dashboard-panel-shadow: var(--shadow-card);
  --dashboard-module-header-height: 40px;
  --dashboard-dense-row-height: 44px;

  --live-color: var(--online);
  --wipe-map-color: var(--primary-colors-secondary);
  --wipe-global-color: var(--primary-colors-main);
  --wipe-update-color: var(--system-colors-gold);
  --capacity-track: var(--background-teritiary);
  --capacity-fill: var(--base-linear-gradiend);

  --map-surface: var(--background-secondary-dark);
  --map-grid-color: color-mix(in srgb, var(--border-color-default) 55%, transparent);
  --map-player-color: var(--primary-colors-secondary);
  --map-squad-color: var(--online);
  --map-label-bg: color-mix(in srgb, var(--background-secondary) 88%, transparent);

  --chat-own-message-bg: var(--primary-colors-secondary-opacity);
  --chat-system-message-bg: var(--background-teritiary);
  --chat-unread-bg: var(--badge-background);
  --squad-health: var(--online);
  --squad-wounded: var(--system-colors-gold);
  --squad-offline: var(--text-disabled);
}
```

## New component specifications

### DashboardPanel

- Same construction as existing card: `--background-secondary`, 1px default border, 8px radius, `--shadow-card`.
- Header height 40px, 12px horizontal padding, 1px bottom border.
- Title 14px / 600 in `--text-main`; optional icon in `--icon-mini`; actions 12px in link colour.
- No clipped corners, industrial rules, terminal labels or uppercase-everything styling.

### CompactServerRow

- 44px minimum height on `--background-secondary`; hover is `--background-teritiary`.
- 8px live dot using `--online`, server name 14px / 600, metadata 12px secondary.
- Capacity bar uses `--background-teritiary` track and the existing brand gradient fill.
- Next-wipe badge uses existing pill/status radius and carries an icon plus label.

### CompactProductCard

- A density variant of existing `.category-card`, not a new card style.
- 12px padding, existing surface/border/radius, 72–88px brand-tinted image well.
- Title 13px / 500; price 14px / 600 in `--primary-colors-secondary`.
- Preserve existing discount, favourite and product-image hover behaviour.

### WipeCalendarCompact

- Existing surface/border/radius.
- Calendar cells use the normal background; today uses a border; event days use 15% semantic tint.
- Map wipe = secondary orange, global wipe = primary red, update = gold. Always include icons or text labels.

### MiniMapPanel

- Map lives inside a standard PROSTOJ card with an 8px inner radius.
- Controls use existing tertiary button styling; server tag uses existing status pill.
- Grid, coordinates and markers use the extension tokens above. The map image may be desaturated slightly, but never receives a black/orange cinematic overlay.

### SquadPanel

- Rows follow existing dropdown/list item treatment: 8px radius, 12px padding, tertiary hover.
- Avatars use `--avatar-radius`; status uses online/gold/disabled semantic colours.
- Health bars use the same geometry as server capacity bars.

### ChatPanel

- Tabs reuse the existing underline-tab pattern: main text with 2px primary underline for active.
- Messages are normal 13px text with 11–12px metadata. Own/system message backgrounds derive from extension tokens.
- Composer reuses existing form input and medium primary icon button styles.

### CompactFooter

- A density variant of the existing footer: `--background-secondary`, 1px top border, 32px height on desktop.
- Copyright and 2–3 links only. Full footer returns on responsive scrolling layouts.

## Content anchors from prostoj.store

- Servers: #1 MAX3 3/100; #2 X2 1/400; #4 PVE 19/50; #5 X10 29/200; #6 X2 21/250; total online 77.
- Products: VIP 399 ₽; Набор рейдера 499 ₽; Набор пирата 299 ₽; Набор киллер 255 ₽; Набор пехотинца 339 ₽; Набор подрывника 299 ₽.
- News: Rust Built Different; Rust май 2026; Twitch Drops Round 49; Rust обновление 02.04; EAC TPM verification failed.

## Hard fidelity constraints

- Use ONLY the existing PROSTOJ semantic tokens and component patterns in this document.
- Default preview must visibly use the original purple/red/orange theme.
- Use Roboto only and the existing real logo/flag/social assets.
- Do not use black/orange brutalism, terminal motifs, clipped corners, custom wordmarks, giant hero blocks, new icon styles or unrelated gaming-dashboard aesthetics.
- Do not hardcode new brand colours inside components. Any missing state must be derived from existing semantic tokens.
- Guest and authenticated screens must look like native additions to the current `prostoj-frontend` site.
