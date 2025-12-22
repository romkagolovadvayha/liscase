'use client';

import { useState } from 'react';
import Input from '@/components/forms/Input';
import Textarea from '@/components/forms/Textarea';
import Select from '@/components/forms/Select';
import Checkbox from '@/components/forms/Checkbox';
import Radio from '@/components/forms/Radio';
import Switch from '@/components/forms/Switch';
import Button from '@/components/forms/Button';
import FormGroup from '@/components/forms/FormGroup';
import AutoComplete from '@/components/design-system/AutoComplete';

export default function FormElements() {
  const [inputValue, setInputValue] = useState('');
  const [textareaValue, setTextareaValue] = useState('');
  const [selectValue, setSelectValue] = useState('');
  const [checkboxChecked, setCheckboxChecked] = useState(false);
  const [switchChecked, setSwitchChecked] = useState(false);
  const [radioValue, setRadioValue] = useState('option1');
  const [autocompleteValue, setAutocompleteValue] = useState('');
  const [autocompleteOptions, setAutocompleteOptions] = useState<Array<{ value: string; label: string }>>([]);
  const [autocompleteNoIconValue, setAutocompleteNoIconValue] = useState('');
  const [autocompleteNoIconOptions, setAutocompleteNoIconOptions] = useState<Array<{ value: string; label: string }>>([]);
  const [userSearchValue, setUserSearchValue] = useState('');
  const [userSearchOptions, setUserSearchOptions] = useState<Array<{ 
    value: string; 
    label: React.ReactNode; 
    avatar?: string;
    username?: string;
    steam_id?: string;
    status?: boolean;
  }>>([]);

  return (
    <div className="space-y-8">
      {/* Input */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Input (Текстовое поле)</h3>
        <div className="space-y-4">
          <FormGroup label="Обычное поле">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Введите текст"
            />
          </FormGroup>

          <FormGroup label="Поле с иконкой слева">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Введите текст"
              leftIcon="info"
            />
          </FormGroup>

          <FormGroup label="Поле с иконкой справа">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Введите текст"
              rightIcon="arrow-right"
            />
          </FormGroup>

          <FormGroup label="Поле поиска (с иконкой)">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Поиск..."
              leftIcon="search"
            />
          </FormGroup>

          <FormGroup label="Поле с ошибкой" error="Это поле обязательно для заполнения">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Введите текст"
              hasError
            />
          </FormGroup>

          <FormGroup label="Отключенное поле">
            <Input
              value="Неизменяемое значение"
              disabled
            />
          </FormGroup>

          <FormGroup label="Поле с подсказкой" hint="Это подсказка для пользователя">
            <Input
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Введите текст"
            />
          </FormGroup>
        </div>
      </div>

      {/* Textarea */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Textarea (Многострочное поле)</h3>
        <div className="space-y-4">
          <FormGroup label="Обычное поле">
            <Textarea
              value={textareaValue}
              onChange={(e) => setTextareaValue(e.target.value)}
              placeholder="Введите текст"
              rows={4}
            />
          </FormGroup>

          <FormGroup label="Поле с ошибкой" error="Максимальная длина 500 символов">
            <Textarea
              value={textareaValue}
              onChange={(e) => setTextareaValue(e.target.value)}
              placeholder="Введите текст"
              rows={4}
              hasError
            />
          </FormGroup>
        </div>
      </div>

      {/* Select */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Select (Выпадающий список)</h3>
        <div className="space-y-4">
          <FormGroup label="Обычный select">
            <Select
              value={selectValue}
              onChange={(e) => setSelectValue(e.target.value)}
            >
              <option value="">Выберите опцию</option>
              <option value="option1">Опция 1</option>
              <option value="option2">Опция 2</option>
              <option value="option3">Опция 3</option>
            </Select>
          </FormGroup>

          <FormGroup label="Select с ошибкой" error="Необходимо выбрать опцию">
            <Select
              value={selectValue}
              onChange={(e) => setSelectValue(e.target.value)}
              hasError
            >
              <option value="">Выберите опцию</option>
              <option value="option1">Опция 1</option>
              <option value="option2">Опция 2</option>
            </Select>
          </FormGroup>

          <FormGroup label="Отключенный select">
            <Select
              value="option1"
              disabled
            >
              <option value="option1">Опция 1</option>
            </Select>
          </FormGroup>
        </div>
      </div>

      {/* AutoComplete */}
      <div>
        <h3 className="text-xl font-semibold mb-4">AutoComplete (Автодополнение)</h3>
        <div className="space-y-4">
          <FormGroup label="AutoComplete с иконкой поиска">
            <AutoComplete
              value={autocompleteValue}
              onChange={(val) => setAutocompleteValue(String(val || ''))}
              placeholder="Введите текст для поиска..."
              showIcon={true}
              options={autocompleteOptions}
              onSearch={(value) => {
                // Имитация поиска
                if (value.length > 0) {
                  setAutocompleteOptions([
                    { value: '1', label: `${value} - Результат 1` },
                    { value: '2', label: `${value} - Результат 2` },
                    { value: '3', label: `${value} - Результат 3` },
                  ]);
                } else {
                  setAutocompleteOptions([]);
                }
              }}
              onSelect={(value, option) => {
                setAutocompleteValue(value);
                setAutocompleteOptions([]);
              }}
            />
          </FormGroup>

          <FormGroup label="AutoComplete без иконки">
            <AutoComplete
              value={autocompleteNoIconValue}
              onChange={(val) => setAutocompleteNoIconValue(String(val || ''))}
              placeholder="Введите текст для поиска..."
              showIcon={false}
              options={autocompleteNoIconOptions}
              onSearch={(value) => {
                // Имитация поиска
                if (value.length > 0) {
                  setAutocompleteNoIconOptions([
                    { value: '1', label: `${value} - Результат 1` },
                    { value: '2', label: `${value} - Результат 2` },
                    { value: '3', label: `${value} - Результат 3` },
                  ]);
                } else {
                  setAutocompleteNoIconOptions([]);
                }
              }}
              onSelect={(value, option) => {
                setAutocompleteNoIconValue(value);
                setAutocompleteNoIconOptions([]);
              }}
            />
          </FormGroup>

          <FormGroup label="Отключенный AutoComplete">
            <AutoComplete
              value="Неизменяемое значение"
              disabled
              showIcon={true}
              placeholder="Отключенное поле"
            />
          </FormGroup>

          <FormGroup label="AutoComplete для поиска пользователя (с аватаркой, ником и Steam ID)">
            <AutoComplete
              value={userSearchValue}
              onChange={(val) => setUserSearchValue(String(val || ''))}
              placeholder="Введите ник или Steam ID..."
              showIcon={true}
              options={userSearchOptions}
              onSearch={(value) => {
                // Имитация поиска пользователей
                if (value.length > 0) {
                  setUserSearchOptions([
                    {
                      value: '76561198000000001',
                      label: (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <div style={{ position: 'relative' }}>
                            <img 
                              src="/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png" 
                              alt="User"
                              style={{
                                width: 24,
                                height: 24,
                                borderRadius: '50%',
                                border: '2px solid var(--icon-main)',
                                objectFit: 'cover'
                              }}
                            />
                            <div style={{
                              position: 'absolute',
                              bottom: -2,
                              right: -2,
                              width: 10,
                              height: 10,
                              background: '#22c55e',
                              border: '2px solid var(--background-secondary)',
                              borderRadius: '50%',
                              zIndex: 2
                            }}></div>
                          </div>
                          <div style={{ display: 'flex', flexDirection: 'column' }}>
                            <span>Игрок {value}</span>
                            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Steam ID: 76561198000000001</span>
                          </div>
                        </div>
                      ),
                      avatar: '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
                      username: `Игрок ${value}`,
                      steam_id: '76561198000000001',
                      status: true,
                    },
                    {
                      value: '76561198000000002',
                      label: (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <div style={{ position: 'relative' }}>
                            <img 
                              src="/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png" 
                              alt="User"
                              style={{
                                width: 24,
                                height: 24,
                                borderRadius: '50%',
                                border: '2px solid var(--icon-main)',
                                objectFit: 'cover'
                              }}
                            />
                          </div>
                          <div style={{ display: 'flex', flexDirection: 'column' }}>
                            <span>Другой игрок {value}</span>
                            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Steam ID: 76561198000000002</span>
                          </div>
                        </div>
                      ),
                      avatar: '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
                      username: `Другой игрок ${value}`,
                      steam_id: '76561198000000002',
                      status: false,
                    },
                  ]);
                } else {
                  setUserSearchOptions([]);
                }
              }}
              onSelect={(value, option) => {
                setUserSearchValue(value);
                setUserSearchOptions([]);
                console.log('Selected user:', option);
              }}
            />
          </FormGroup>
        </div>
      </div>

      {/* Checkbox */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Checkbox (Чекбокс)</h3>
        <div className="space-y-4">
          <FormGroup>
            <Checkbox
              checked={checkboxChecked}
              onChange={(e) => setCheckboxChecked(e.target.checked)}
              label="Согласен с условиями"
            />
          </FormGroup>

          <FormGroup>
            <Checkbox
              checked={true}
              disabled
              label="Отключенный чекбокс (выбран)"
            />
          </FormGroup>

          <FormGroup>
            <Checkbox
              checked={false}
              disabled
              label="Отключенный чекбокс (не выбран)"
            />
          </FormGroup>
        </div>
      </div>

      {/* Radio */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Radio (Радиокнопка)</h3>
        <div className="space-y-4">
          <FormGroup label="Выберите опцию">
            <div className="space-y-2">
              <Radio
                name="radio-group"
                value="option1"
                checked={radioValue === 'option1'}
                onChange={(e) => setRadioValue(e.target.value)}
                label="Опция 1"
              />
              <Radio
                name="radio-group"
                value="option2"
                checked={radioValue === 'option2'}
                onChange={(e) => setRadioValue(e.target.value)}
                label="Опция 2"
              />
              <Radio
                name="radio-group"
                value="option3"
                checked={radioValue === 'option3'}
                onChange={(e) => setRadioValue(e.target.value)}
                label="Опция 3"
              />
            </div>
          </FormGroup>
        </div>
      </div>

      {/* Switch */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Switch (Переключатель)</h3>
        <div className="space-y-4">
          <FormGroup>
            <Switch
              checked={switchChecked}
              onChange={(e) => setSwitchChecked(e.target.checked)}
              label="Включить уведомления"
            />
          </FormGroup>

          <FormGroup>
            <Switch
              checked={true}
              disabled
              label="Отключенный переключатель (включен)"
            />
          </FormGroup>

          <FormGroup>
            <Switch
              checked={false}
              disabled
              label="Отключенный переключатель (выключен)"
            />
          </FormGroup>

          <FormGroup>
            <Switch
              checked={switchChecked}
              onChange={(e) => setSwitchChecked(e.target.checked)}
            />
            <span className="ml-3">Переключатель без текста</span>
          </FormGroup>
        </div>
      </div>

      {/* Buttons */}
      <div>
        <h3 className="text-xl font-semibold mb-4">Button (Кнопки)</h3>
        <div className="space-y-4">
          <div className="flex flex-wrap gap-4">
            <Button variant="primary">Primary кнопка</Button>
            <Button variant="secondary">Secondary кнопка</Button>
            <Button variant="tertiary">Tertiary кнопка</Button>
          </div>

          <div className="flex flex-wrap gap-4">
            <Button variant="primary" disabled>Отключенная Primary</Button>
            <Button variant="secondary" disabled>Отключенная Secondary</Button>
            <Button variant="tertiary" disabled>Отключенная Tertiary</Button>
          </div>

          <div className="flex flex-wrap gap-4">
            <Button variant="primary" size="small">Маленькая кнопка</Button>
            <Button variant="primary" size="medium">Средняя кнопка</Button>
          </div>

          <div className="flex flex-wrap gap-4">
            <Button variant="primary" leftIcon="arrow-right">Кнопка с иконкой слева</Button>
            <Button variant="secondary" rightIcon="arrow-right">Кнопка с иконкой справа</Button>
            <Button variant="primary" leftIcon="steam" faIconSize="lg">Войти через Steam</Button>
          </div>

          <div className="flex flex-wrap gap-4">
            <Button as="a" href="#" variant="primary" leftIcon="arrow-right">Ссылка-кнопка с иконкой</Button>
            <Button as="a" href="#" variant="secondary" rightIcon="arrow-right">Ссылка-кнопка</Button>
          </div>

          <div className="flex flex-wrap gap-4">
            <Button variant="primary" loading>Загрузка...</Button>
            <Button variant="secondary" loading>Отправка</Button>
            <Button variant="primary" loading leftIcon="arrow-right">Сохранение</Button>
          </div>
        </div>
      </div>
    </div>
  );
}

