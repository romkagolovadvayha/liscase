import MarketSkinsClient from '@/components/market/MarketSkinsClient';

export const dynamic = 'force-dynamic';

export const metadata = {
  title: 'Маркет скинов',
  description: 'Купить скины для Rust',
};

export default function MarketSkinsPage() {
  return <MarketSkinsClient />;
}

