'use client';

import { config } from '@fortawesome/fontawesome-svg-core';
import '@fortawesome/fontawesome-svg-core/styles.css';

// Tell Font Awesome to skip adding the CSS automatically since it's already imported above
config.autoAddCss = false;

export default function FontAwesomeProvider({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}

