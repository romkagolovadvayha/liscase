'use client';

import { useState } from 'react';
import UserStats from '@/components/homepage/UserStats';
import DailyReward from '@/components/homepage/DailyReward';
import Search from '@/components/homepage/Search';
import Categories from '@/components/homepage/Categories';
import ProductCard from '@/components/homepage/ProductCard';
import BlogCard from '@/components/homepage/BlogCard';

export default function HomepageElements() {
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);

  const categories = [
    { id: 0, name: 'Все', image: undefined },
    { id: 1, name: 'Оружие', image: '/images/categories/color_guns.png' },
    { id: 2, name: 'Ресурсы', image: '/images/categories/color_resorce.png' },
    { id: 3, name: 'Инструменты', image: '/images/categories/color_instruments.png' },
    { id: 4, name: 'Еда', image: '/images/categories/color_food.png' },
    { id: 5, name: 'Постройки', image: '/images/categories/color_postoiki.png' },
    { id: 6, name: 'Компоненты', image: '/images/categories/color_components.png' },
    { id: 7, name: 'Электрика', image: '/images/categories/color_electrics.png' },
    { id: 8, name: 'Ткань', image: '/images/categories/color_clotch.png' },
    { id: 9, name: 'Медицина', image: '/images/categories/color_med.png' },
    { id: 10, name: 'Фермер', image: '/images/categories/color_fermer.png' },
    { id: 11, name: 'Набор', image: '/images/categories/color_nabor.png' },
    { id: 12, name: 'Прочее', image: '/images/categories/color_prochee.png' },
  ];

  const products = [
    {
      id: 958,
      name: 'Набор рейдера',
      image: '/uploads/drop150/958_1_07350b3fb4ec5c805b2d7ec781fea70a.png',
      price: 0,
      priceReal: 499,
      categoryId: 1,
    },
    {
      id: 960,
      name: 'Набор киллер',
      image: '/uploads/drop150/960_1_5406383335d63592e0c8aa7ff9e6e266.png',
      price: 0,
      priceReal: 210,
      categoryId: 1,
    },
    {
      id: 949,
      name: 'Карты доступа',
      image: '/uploads/drop150/949_1_bdb3d0366d9b815e0c200f6aaabdc42d.png',
      price: 0,
      priceReal: 0,
      categoryId: 1,
    },
    {
      id: 955,
      name: 'Набор чаев',
      image: '/uploads/drop150/955_1_8d4a827c794187c8144b3c2f5f395a45.png',
      price: 110.00,
      priceReal: 99,
      discount: 10,
      categoryId: 1,
    },
    {
      id: 963,
      name: 'Пироги',
      image: '/uploads/drop150/963_1_5b83d8c95ffe2f7cdad29c116b807599.png',
      price: 0,
      priceReal: 0,
      categoryId: 1,
    },
    {
      id: 956,
      name: 'Набор электрика',
      image: '/uploads/drop150/956_1_393afacf06e6b316624844cf96f02012.png',
      price: 0,
      priceReal: 189,
      categoryId: 1,
    },
    {
      id: 959,
      name: 'Набор ресурсов',
      image: '/uploads/drop150/959_1_8aa936eec0150b2947f4fc10b825d205.png',
      price: 0,
      priceReal: 1490,
      categoryId: 1,
    },
    {
      id: 954,
      name: 'Набор сталкера',
      image: '/uploads/drop150/954_1_ec095789736fc52b400cbfb1a56dba5c.png',
      price: 0,
      priceReal: 150,
      categoryId: 1,
    },
    {
      id: 957,
      name: 'Набор компонентов',
      image: '/uploads/drop150/957_1_a9203e2eebf94c3db4b8578f5bd0e47e.png',
      price: 0,
      priceReal: 790,
      categoryId: 1,
    },
    {
      id: 953,
      name: 'Набор фермера',
      image: '/uploads/drop150/953_1_60ec11d5969b2a3b85bff69e39865d43.png',
      price: 0,
      priceReal: 99,
      categoryId: 1,
    },
    {
      id: 950,
      name: 'Скины',
      image: '/uploads/drop150/950_1_1fa96608a83a52e2a23526b1e39f241b.png',
      price: 0,
      priceReal: 0,
      categoryId: 1,
    },
    {
      id: 951,
      name: 'Чертежи',
      image: '/uploads/drop150/951_1_7ffd2f788eaec4054c58c991cf716cb4.png',
      price: 0,
      priceReal: 0,
      categoryId: 1,
    },
    {
      id: 852,
      name: 'Миникоптер',
      image: '/uploads/drop150/852_16c68cea35462cf1059f78395043fd3c.png',
      price: 0,
      priceReal: 190,
      categoryId: 14,
    },
    {
      id: 477,
      name: 'Медицинский шприц',
      image: '/uploads/drop150/477_7f1d1a8ab08cbb23361936817800e49d.png',
      price: 14.00,
      priceReal: 13,
      discount: 10,
      count: 5,
      categoryId: 7,
    },
    {
      id: 599,
      name: 'Пистолетный патрон',
      image: '/uploads/drop150/599_5ea0b0e214c89d8903505c70841819ff.png',
      price: 25.00,
      priceReal: 23,
      discount: 10,
      count: 100,
      categoryId: 4,
    },
    {
      id: 305,
      name: 'Металлолом',
      image: '/uploads/drop150/305_6949c48b05801055b644b5938b1c427c.png',
      price: 40.00,
      priceReal: 28,
      discount: 30,
      count: 100,
      categoryId: 3,
    },
    {
      id: 320,
      name: 'Фрагменты металла',
      image: '/uploads/drop150/320_8c8ddd3828a0df1fbe3d6440f17b1469.png',
      price: 30.00,
      priceReal: 27,
      discount: 10,
      count: 1000,
      categoryId: 3,
    },
    {
      id: 316,
      name: 'Топливо низкого качества',
      image: '/uploads/drop150/316_2100bf9fb40da18f4d1c972453e9cd61.png',
      price: 25.00,
      priceReal: 23,
      discount: 10,
      count: 100,
      categoryId: 3,
    },
    {
      id: 315,
      name: 'Ткань',
      image: '/uploads/drop150/315_c17c81d396ce4e0272dfe9bb970dfb3d.png',
      price: 35.00,
      priceReal: 32,
      discount: 10,
      count: 300,
      categoryId: 3,
    },
    {
      id: 295,
      name: 'Дерево',
      image: '/uploads/drop150/295_f43e705790003ee29b962e8ab921eb16.png',
      price: 26.00,
      priceReal: 24,
      discount: 10,
      count: 5000,
      categoryId: 3,
    },
    {
      id: 148,
      name: 'Деревянная лестница',
      image: '/uploads/drop150/148_413377e6ea7f3b5b7b2211e58de316cb.png',
      price: 0,
      priceReal: 25,
      count: 5,
      categoryId: 6,
    },
    {
      id: 300,
      name: 'Камни',
      image: '/uploads/drop150/300_2a1ca11dc57b4a22968d9a5aa8c21ec8.png',
      price: 0,
      priceReal: 26,
      count: 3000,
      categoryId: 3,
    },
    {
      id: 304,
      name: 'Металл высокого качества',
      image: '/uploads/drop150/304_e055a8a0dcc3c21bf1ebad1e654fc1dd.png',
      price: 90.00,
      priceReal: 81,
      discount: 10,
      count: 100,
      categoryId: 3,
    },
    {
      id: 867,
      name: 'High Caliber Револьвер',
      image: '/uploads/drop150/867_71af105f157abedb71b3eee24e51308f.png',
      price: 0,
      priceReal: 29,
      categoryId: 2,
    },
    {
      id: 868,
      name: 'Акваскутер',
      image: '/uploads/drop150/868_e648b7fbee45fd2e1c19b14124de2e06.png',
      price: 30.00,
      priceReal: 27,
      discount: 10,
      categoryId: 14,
    },
    {
      id: 598,
      name: 'Патроны 5.56-мм',
      image: '/uploads/drop150/598_777ec2aa3120a69bcb3d5818de1cc7d3.png',
      price: 35.00,
      priceReal: 32,
      discount: 10,
      count: 100,
      categoryId: 4,
    },
    {
      id: 602,
      name: 'Ракета',
      image: '/uploads/drop150/602_0736169c589c86cc02ce3f46ad8edfd4.png',
      price: 0,
      priceReal: 310,
      count: 4,
      categoryId: 4,
    },
    {
      id: 446,
      name: 'C4',
      image: '/uploads/drop150/446_3da715bdfd205544821f8969040465c2.png',
      price: 0,
      priceReal: 300,
      count: 2,
      categoryId: 4,
    },
    {
      id: 62,
      name: 'MP5A4',
      image: '/uploads/drop150/62_9852a9ab9edae6eb72454061fee343cb.png',
      price: 59.00,
      priceReal: 54,
      discount: 10,
      categoryId: 2,
    },
    {
      id: 70,
      name: 'Винтовка',
      image: '/uploads/drop150/70_838bfc7a435d3139b508347dcf88abfe.png',
      price: 99.00,
      priceReal: 95,
      discount: 5,
      categoryId: 2,
    },
  ];

  const blogPosts = [
    {
      id: 1,
      title: 'Новое обновление сервера',
      description: 'Добавлены новые предметы и улучшена производительность',
      image: 'https://prostoj.store/uploads/blog/20251204_170858_p1RKJ-ep_SPOILER_png-klev-club-klrr-p-potracheno-png-6.png',
      category: 'Новости',
      date: '15.01.2024',
      url: '/posts/1',
    },
    {
      id: 2,
      title: 'Гайд по выживанию',
      description: 'Узнайте, как выжить в первые дни на сервере',
      image: 'https://prostoj.store/uploads/blog/20251128_110449_yYpeLyvQ_photo_2025-11-28_12-04-38.jpg',
      category: 'Гайды',
      date: '12.01.2024',
      url: '/posts/2',
    },
  ];

  return (
    <div className="space-y-12">
      {/* Для неавторизованного пользователя */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Для неавторизованного пользователя</h3>
        <div className="info">
          <UserStats
            isGuest={true}
            projectStats={{
              users: 12500,
              online: 850,
              count: 12,
            }}
            serverActiveTag="main"
          />
          <DailyReward botLink="https://t.me/bot" />
        </div>
      </div>

      {/* Для авторизованного пользователя */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Для авторизованного пользователя</h3>
        <div className="info">
          <UserStats
            isGuest={false}
            username="Player123"
            userStats={{
              kills: 1250,
              deaths: 450,
              kd: 2.78,
              scientists: 320,
              'sulfur.ore': 50000,
              'metal.ore': 75000,
              stones: 100000,
              wood: 150000,
            }}
            awards={[
              { id: 1, name: 'Первая кровь', image: '/images/awards/award1.png', completed: true },
              { id: 2, name: 'Снайпер', image: '/images/awards/award2.png', completed: true },
            ]}
            awardsStats={{ completed: 5, total: 20 }}
            activeVip={{
              expires_at: '2024-12-31 23:59:59',
            }}
            activeVipTimestamp={1735689599}
            serverActiveTag="main"
          />
          <DailyReward botLink="https://t.me/bot" />
        </div>
      </div>

      {/* Search */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Search (Поиск)</h3>
        <Search placeholder="Введите название предмета.." onSearch={(value) => console.log('Search:', value)} />
      </div>

      {/* Categories */}
      <div style={{ width: '100%', maxWidth: '100%', overflow: 'hidden' }}>
        <h3 className="text-xl font-semibold mb-4">Categories (Категории)</h3>
        <Categories
          categories={categories}
          activeCategoryId={selectedCategory}
          onCategoryClick={(id) => setSelectedCategory(id)}
        />
      </div>

      {/* Products */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Product Card (Карточка продукта)</h3>
        <div className="category__cards">
          {products.map((product) => (
            <ProductCard key={product.id} {...product} onClick={(id) => console.log('Product clicked:', id)} />
          ))}
        </div>
      </div>

      {/* Blog */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Blog Card (Карточка блога)</h3>
        <div className="home-blog-grid">
          {blogPosts.map((post) => (
            <BlogCard key={post.id} {...post} />
          ))}
        </div>
      </div>
    </div>
  );
}

