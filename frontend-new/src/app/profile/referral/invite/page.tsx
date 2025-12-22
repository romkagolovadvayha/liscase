import { Metadata } from 'next';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

export const metadata: Metadata = {
  title: 'Как приглашать? - Реферальная система - Liscase',
  description: 'Как приглашать друзей в реферальную систему',
};

export default async function ProfileReferralInvitePage() {
  return <ProfileReferralClient />;
}






