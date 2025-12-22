/**
 * Форматирует URL изображения с учетом CDN (как в старой версии getImagePubUrl)
 * В старой версии: site_cdnUrl + "/uploads" + image
 * @param imageUrl - URL изображения из базы (например: /drop/123.png)
 * @param cdnUrl - CDN URL из настроек (site_cdnUrl)
 * @returns Отформатированный URL
 */
export function formatImageUrl(imageUrl: string | null | undefined, cdnUrl?: string | null): string {
  if (!imageUrl) {
    return '';
  }

  // Если URL уже полный (начинается с http:// или https://), возвращаем как есть
  if (imageUrl.startsWith('http://') || imageUrl.startsWith('https://')) {
    return imageUrl;
  }

  // Формируем путь как в старой версии: /uploads + путь из базы
  const imagePath = imageUrl.startsWith('/uploads') ? imageUrl : `/uploads${imageUrl}`;

  // Если есть CDN URL, добавляем его
  if (cdnUrl) {
    // Убираем слэш в конце CDN URL, если есть
    const cleanCdnUrl = cdnUrl.replace(/\/$/, '');
    return `${cleanCdnUrl}${imagePath}`;
  }

  // Если нет CDN URL, возвращаем путь с /uploads
  return imagePath;
}

