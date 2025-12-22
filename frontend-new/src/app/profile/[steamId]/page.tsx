import PlayerProfileClient from '@/components/profile/PlayerProfileClient';
import ProfilePageWrapper from '@/components/profile/ProfilePageWrapper';
import '@/styles/profile-player.scss';
import { notFound } from 'next/navigation';
import { getPlayerProfileData } from '@/lib/profile';

export const revalidate = 3600; // Кешировать на 1 час

export default async function PlayerProfilePage({
  params,
}: {
  params: Promise<{ steamId: string }>;
}) {
  const { steamId } = await params;
  console.log(`[PlayerProfilePage] Rendering page for steamId: ${steamId}`);

  return (
    <ProfilePageWrapper>
      <div className="player-profile-page">
        <PlayerProfileContent steamId={steamId} />
      </div>
    </ProfilePageWrapper>
  );
}

async function PlayerProfileContent({ steamId }: { steamId: string }) {
  console.log(`[PlayerProfileContent] Fetching data for steamId: ${steamId}`);
  
  try {
    const data = await getPlayerProfileData(steamId);
    
    console.log(`[PlayerProfileContent] Data received:`, data ? 'success' : 'null');

    if (!data) {
      console.error(`[PlayerProfileContent] Data is null for steamId: ${steamId}, calling notFound()`);
      notFound();
    }

    return <PlayerProfileClient initialData={data} />;
  } catch (error: any) {
    console.error(`[PlayerProfileContent] Error for steamId ${steamId}:`, error);
    console.error('[PlayerProfileContent] Error stack:', error?.stack);
    notFound();
  }
}

