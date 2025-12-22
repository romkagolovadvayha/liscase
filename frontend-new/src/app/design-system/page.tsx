/**
 * Страница дизайн-системы
 * Демонстрация всех компонентов из старой версии
 */
import FormElements from '@/components/design-system/FormElements';
import HomepageElements from '@/components/design-system/HomepageElements';
import TypographyElements from '@/components/design-system/TypographyElements';
import TooltipElements from '@/components/design-system/TooltipElements';
import ServerElements from '@/components/design-system/ServerElements';
import ThemeSwitcher from '@/components/design-system/ThemeSwitcher';
import '@/styles/design-system.scss';

export default function DesignSystemPage() {
  return (
    <div className="design-system-page">
      <div className="design-system-layout">
        <div className="design-system-content">
          <div className="design-system-header">
            <h1 className="page-title">Дизайн-система</h1>
            <ThemeSwitcher />
          </div>
          
          <div className="design-system-sections">
            {/* Типографика */}
            <section className="design-system-section">
              <h2 className="design-system-section-title">Типографика</h2>
              <TypographyElements />
            </section>

            {/* Тултипы */}
            <section className="design-system-section">
              <h2 className="design-system-section-title">Тултипы и подсказки</h2>
              <TooltipElements />
            </section>

            {/* Элементы формы */}
            <section className="design-system-section">
              <h2 className="design-system-section-title">Элементы формы</h2>
              <FormElements />
            </section>

            {/* Серверы */}
            <section className="design-system-section">
              <h2 className="design-system-section-title">Серверы</h2>
              <ServerElements />
            </section>
            
            {/* Элементы главной страницы */}
            <section className="design-system-section">
              <h2 className="design-system-section-title">Элементы главной страницы</h2>
              <HomepageElements />
            </section>
          </div>
        </div>
      </div>
    </div>
  );
}

