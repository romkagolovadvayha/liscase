import { NextRequest } from 'next/server';
import { redirect } from 'next/navigation';
import BlogPostPage from './BlogPostPage';

interface PageProps {
  params: Promise<{
    slug: string[];
  }>;
}

// Обработка старых URL формата /posts/{categoryLinkName}/{categoryLinkNameChild?}/post-{blogLinkName}
async function getPostData(slug: string[]) {
  if (slug.length === 0) {
    return null;
  }

  const lastSegment = slug[slug.length - 1];
  
  // Если последний сегмент начинается с "post-", извлекаем linkName
  if (lastSegment.startsWith('post-')) {
    const linkName = lastSegment.replace(/^post-/, '');
    return { linkName };
  }

  // Если это не пост, а категория - возвращаем null
  return null;
}

export default async function BlogPostRoute({ params }: PageProps) {
  const { slug } = await params;
  
  // Если это корневой путь /posts, рендерим список
  if (slug.length === 0) {
    const { default: BlogPage } = await import('../page');
    return <BlogPage />;
  }

  const postData = await getPostData(slug);
  
  if (!postData) {
    // Если это не пост (не начинается с post-), это категория или подкатегория
    // Редиректим на соответствующий роут
    // /posts/{category} -> /posts/[categoryLinkName]
    // /posts/{category}/{subcategory} -> /posts/[categoryLinkName]/[subcategoryLinkName]
    if (slug.length === 1) {
      redirect(`/posts/${slug[0]}`);
    } else if (slug.length === 2) {
      redirect(`/posts/${slug[0]}/${slug[1]}`);
    } else {
      redirect('/posts');
    }
    return null;
  }

  // Это пост - отображаем страницу поста
  return <BlogPostPage linkName={postData.linkName} slug={slug} />;
}
