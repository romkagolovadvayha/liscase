import { Metadata } from 'next';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

export const metadata: Metadata = {
  title: 'Реферальная система - Liscase',
  description: 'Реферальная система',
};

export default async function ProfileReferralPage() {
  return <ProfileReferralClient />;
}






