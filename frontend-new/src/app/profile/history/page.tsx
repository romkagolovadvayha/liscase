import { Metadata } from 'next';
import ProfileHistoryClient from '@/components/profile/ProfileHistoryClient';

export const metadata: Metadata = {
  title: 'История операций - Liscase',
  description: 'История операций пользователя',
};

export default async function ProfileHistoryPage() {
  return <ProfileHistoryClient />;
}






