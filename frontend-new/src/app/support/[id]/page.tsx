'use client';

import React from 'react';
import { useParams } from 'next/navigation';
import SupportTicketClient from '@/components/support/SupportTicketClient';

export default function SupportTicketPage() {
  const params = useParams();
  const id = params?.id as string;
  const ticketNumber = id ? parseInt(id, 10) : NaN;

  if (isNaN(ticketNumber)) {
    return null;
  }

  return <SupportTicketClient ticketNumber={ticketNumber} />;
}
