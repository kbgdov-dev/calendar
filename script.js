document.addEventListener('DOMContentLoaded', () => {

    // --- 1. Глобальные переменные и константы ---
    
    const MONTH_NAMES = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    const WEEK_DAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    // Китайский зодиак
    const CHINESE_ZODIAC = [
        'Крысы', 'Быка', 'Тигра', 'Кролика', 'Дракона', 'Змеи',
        'Лошади', 'Козы', 'Обезьяны', 'Петуха', 'Собаки', 'Свиньи'
    ];

    /**
     * Проверяет, является ли год високосным
     */
    function isLeapYear(year) {
        return (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
    }

    /**
     * Определяет животное по китайскому гороскопу
     */
    function getChineseZodiac(year) {
        // Китайский календарь начинается с 1924 года (год Крысы)
        // 1924 = Крыса, 1925 = Бык, и т.д.
        const baseYear = 1924;
        const index = (year - baseYear) % 12;
        return CHINESE_ZODIAC[index];
    }

    /**
     * Обновляет информацию о годе (високосный, китайский зодиак)
     */
    function updateYearInfo(year) {
        const yearInfoEl = document.getElementById('year-info');
        if (!yearInfoEl) return;

        const leap = isLeapYear(year);
        const zodiac = getChineseZodiac(year);

        yearInfoEl.innerHTML = `
            <div class="year-info-item">
                ${leap ? '📅 Високосный год' : '📅 Обычный год'}
            </div>
            <div class="year-info-item">
                🐉 Год ${zodiac}
            </div>
        `;
    }

    // Получаем сегодняшнюю дату в ЛОКАЛЬНОМ времени
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    // Теперь today это начало дня (00:00:00) в ЛОКАЛЬНОМ времени
    
    // Отладочная информация
    console.log('🗓️ Текущая дата/время:', now);
    console.log('🗓️ Сегодняшняя дата (нормализованная):', today);
    console.log('📅 Год:', today.getFullYear(), 'Месяц:', today.getMonth(), 'День:', today.getDate());
    console.log('⏰ Timestamp:', today.getTime());

    let allEvents = []; // Здесь будем хранить все загруженные события
    let baseEvents = []; // Базовые события из events.json
    let currentYear; // Текущий выбранный год
    let isLoading = false; // Флаг загрузки

    // Получаем ссылки на DOM-элементы
    const yearInput = document.getElementById('year-input');
    const yearView = document.getElementById('year-view');
    const monthNameEl = document.getElementById('month-name');
    const largeMonthGrid = document.getElementById('large-month-grid');
    const modal = document.getElementById('event-modal');
    const modalDateEl = document.getElementById('modal-date');
    const modalEventsList = document.getElementById('modal-events-list');
    
    // Элементы панели редактирования
    const editPanel = document.getElementById('edit-panel');
    const editForm = document.getElementById('edit-form');
    const editCancelBtn = document.getElementById('edit-cancel-btn');
    const editDeleteBtn = document.getElementById('edit-delete-btn');
    const editAllDayCheckbox = document.getElementById('edit-all-day');
    const editTimeInput = document.getElementById('edit-time');
    
    // Toast уведомления
    const toastContainer = document.getElementById('toast-container');
    
    // Модалка подтверждения удаления
    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    
    // Переменная для хранения редактируемого события
    let editingEventIndex = -1;
    let editingEventDate = null; // Храним дату редактируемого события

    // --- 1.5 Функции для toast уведомлений ---
    
    /**
     * Показывает toast уведомление
     * @param {string} message - Текст сообщения
     * @param {string} type - Тип уведомления: 'success', 'error', 'info'
     */
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Иконки для разных типов
        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <span class="toast-message">${message}</span>
        `;
        
        toastContainer.appendChild(toast);
        
        // Анимация появления
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Автоматическое удаление через 3 секунды
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // --- 1.6 Функции для редактирования событий ---

    /**
     * Сохраняет события на сервер
     * @param {number} year - Год для сохранения
     * @param {Array} events - Массив событий для сохранения
     */
    async function saveEventsToServer(year, events) {
        try {
            console.log('💾 Отправка событий на сервер:', { year, count: events.length });

            const response = await fetch('save_events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    year: year,
                    events: events
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ошибка: ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Неизвестная ошибка сервера');
            }

            console.log('✅ События успешно сохранены на сервере:', result);
            return true;

        } catch (error) {
            console.error('❌ Ошибка сохранения на сервер:', error);
            showToast('Ошибка сохранения: ' + error.message, 'error');
            return false;
        }
    }

    /**
     * Фильтрует события для конкретного года и сохраняет их на сервер
     * @param {number} year - Год для сохранения
     */
    async function saveEventsForYear(year) {
        // Фильтруем события для указанного года
        const yearStr = String(year);
        const eventsForYear = allEvents.filter(event => {
            // Проверяем события с полной датой (YYYY-MM-DD)
            if (event.date.startsWith(yearStr + '-')) {
                return true;
            }
            // Для событий без года (MM-DD) - включаем их во все годы
            if (event.date.match(/^\d{2}-\d{2}$/)) {
                // Добавляем год к дате перед сохранением
                return true;
            }
            return false;
        });

        // Преобразуем события для сохранения
        const eventsToSave = eventsForYear.map(event => {
            // Если дата без года (MM-DD), оставляем как есть - это базовое событие
            // Если дата с годом (YYYY-MM-DD), оставляем полную дату
            return {
                title: event.title,
                date: event.date,
                time: event.time,
                color: event.color || '#4A90E2'
            };
        });

        console.log(`📤 Сохранение ${eventsToSave.length} событий для ${year} года`);
        return await saveEventsToServer(year, eventsToSave);
    }

    /**
     * Открывает панель редактирования для указанного события
     */
    function openEditPanel(eventIndex, event) {
        console.log('✏️ Открываю панель редактирования для события:', event);
        
        editingEventIndex = eventIndex;
        editingEventDate = event.date; // Сохраняем дату в переменной
        
        // Заполняем форму данными события
        document.getElementById('edit-title').value = event.title;
        // edit-date убрано - дата не редактируется
        
        // Обрабатываем время
        if (event.time === 'Весь день') {
            editAllDayCheckbox.checked = true;
            editTimeInput.value = '';
            editTimeInput.disabled = true;
        } else {
            editAllDayCheckbox.checked = false;
            editTimeInput.value = event.time;
            editTimeInput.disabled = false;
        }
        
        // Выбираем цвет
        const colorInputs = document.querySelectorAll('input[name="color"]');
        colorInputs.forEach(input => {
            if (input.value === event.color) {
                input.checked = true;
            }
        });
        
        // Показываем кнопку удаления для существующих событий
        editDeleteBtn.style.display = 'block';
        
        // Показываем панель
        editPanel.classList.add('active');
    }
    
    /**
     * Закрывает панель редактирования
     */
    function closeEditPanel() {
        console.log('❌ Закрываю панель редактирования');
        editPanel.classList.remove('active');
        editingEventIndex = -1;
        editingEventDate = null; // Сбрасываем дату
        editForm.reset();
    }
    
    /**
     * Удаляет событие
     */
    function deleteEvent() {
        if (editingEventIndex === -1) {
            console.error('❌ Нет события для удаления!');
            showToast('Ошибка: нет события для удаления', 'error');
            return;
        }
        
        const event = allEvents[editingEventIndex];
        
        // Показываем модалку подтверждения
        deleteConfirmModal.classList.add('active');
        
        // Обработчик подтверждения удаления
        const confirmHandler = async () => {
            console.log('🗑️ Удаляю событие:', event);

            // Сохраняем дату для обновления
            const eventDate = event.date;

            // Удаляем событие из массива
            allEvents.splice(editingEventIndex, 1);

            console.log('✅ Событие удалено из массива');

            // Определяем год для сохранения
            const [year] = eventDate.split('-');
            const yearToSave = year.length === 4 ? parseInt(year) : currentYear;

            // Сохраняем изменения на сервер
            const saved = await saveEventsForYear(yearToSave);

            if (saved) {
                showToast('Событие успешно удалено', 'success');
            } else {
                showToast('Событие удалено, но не сохранено на сервер', 'error');
            }

            // Закрываем модалку подтверждения
            deleteConfirmModal.classList.remove('active');

            // Закрываем панель редактирования
            closeEditPanel();

            // Проверяем, остались ли события на эту дату
            const dateStrShort = eventDate.split('-').slice(-2).join('-');
            const remainingEvents = allEvents.filter(e =>
                e.date === eventDate || e.date === dateStrShort
            );

            if (remainingEvents.length === 0) {
                // Если событий не осталось, закрываем модальное окно
                hideEventModal();
            } else {
                // Обновляем список событий в модальном окне
                showEventsForDate(eventDate);
            }

            // Обновляем календари
            renderYearView(currentYear);

            // Определяем месяц удаленного события
            const [yearStr, month] = eventDate.split('-');
            const monthIndex = parseInt(month) - 1;
            const eventYear = yearStr.length === 4 ? parseInt(yearStr) : currentYear;
            renderLargeMonthView(eventYear, monthIndex);

            console.log('✅ Календари обновлены после удаления');

            // Удаляем обработчики
            confirmDeleteBtn.removeEventListener('click', confirmHandler);
            cancelDeleteBtn.removeEventListener('click', cancelHandler);
        };
        
        // Обработчик отмены удаления
        const cancelHandler = () => {
            deleteConfirmModal.classList.remove('active');
            confirmDeleteBtn.removeEventListener('click', confirmHandler);
            cancelDeleteBtn.removeEventListener('click', cancelHandler);
        };
        
        // Добавляем обработчики
        confirmDeleteBtn.addEventListener('click', confirmHandler);
        cancelDeleteBtn.addEventListener('click', cancelHandler);
    }
    
    /**
     * Сохраняет изменения события или создает новое
     */
    async function saveEventChanges(e) {
        e.preventDefault();

        // Собираем данные из формы
        const title = document.getElementById('edit-title').value.trim();
        const allDay = editAllDayCheckbox.checked;
        const time = allDay ? 'Весь день' : editTimeInput.value;
        const colorInput = document.querySelector('input[name="color"]:checked');
        const color = colorInput ? colorInput.value : '#E74C3C';

        // Используем сохраненную дату
        const date = editingEventDate;

        // Валидация
        if (!title) {
            showToast('Пожалуйста, введите название события', 'error');
            document.getElementById('edit-title').classList.add('error');
            setTimeout(() => {
                document.getElementById('edit-title').classList.remove('error');
            }, 2000);
            return;
        }

        if (!date) {
            showToast('Ошибка: дата события не определена', 'error');
            return;
        }

        if (!allDay && !time) {
            showToast('Укажите время события или отметьте "Весь день"', 'error');
            editTimeInput.classList.add('error');
            setTimeout(() => {
                editTimeInput.classList.remove('error');
            }, 2000);
            return;
        }

        // Показываем loader
        const saveButton = editForm.querySelector('.btn-save');
        const originalText = saveButton.textContent;
        saveButton.disabled = true;
        saveButton.textContent = '⏳ Сохранение...';

        // Выполняем сохранение
        try {
            if (editingEventIndex === -1) {
                // Создаем новое событие
                console.log('➕ Создаю новое событие:', { title, date, time, color });

                const newEvent = {
                    title,
                    date,
                    time,
                    color
                };

                allEvents.push(newEvent);
                console.log('✅ Новое событие создано в памяти');
            } else {
                // Обновляем существующее событие
                console.log('💾 Сохраняю изменения события:', { title, date, time, color });

                allEvents[editingEventIndex] = {
                    ...allEvents[editingEventIndex],
                    title,
                    date,
                    time,
                    color
                };

                console.log('✅ Событие обновлено в памяти:', allEvents[editingEventIndex]);
            }

            // Определяем год для сохранения
            const [year] = date.split('-');
            const yearToSave = year.length === 4 ? parseInt(year) : currentYear;

            // Сохраняем на сервер
            const saved = await saveEventsForYear(yearToSave);

            if (saved) {
                showToast(editingEventIndex === -1 ? 'Событие успешно создано' : 'Событие успешно обновлено', 'success');
            } else {
                showToast('Событие сохранено локально, но не на сервере', 'error');
            }

        } catch (error) {
            console.error('❌ Ошибка при сохранении:', error);
            showToast('Ошибка при сохранении события', 'error');
        } finally {
            // Восстанавливаем кнопку
            saveButton.disabled = false;
            saveButton.textContent = originalText;

            // Закрываем панель
            closeEditPanel();

            // Обновляем отображение модального окна
            showEventsForDate(date);

            // Обновляем календари
            renderYearView(currentYear);

            // Определяем месяц события
            const [year, month] = date.split('-');
            const monthIndex = parseInt(month) - 1;
            const eventYear = year.length === 4 ? parseInt(year) : currentYear;
            renderLargeMonthView(eventYear, monthIndex);

            console.log('✅ Календари обновлены');
        }
    }

    // --- 2. Инициализация и загрузка данных ---

    /**
     * Главная функция инициализации.
     */
    async function init() {
        // Устанавливаем ТЕКУЩИЙ год и месяц по умолчанию
        const initialDate = new Date();
        currentYear = initialDate.getFullYear();
        yearInput.value = currentYear;
        
        // Сначала загружаем базовые события из events.json
        await loadBaseEvents();
        
        // Затем пытаемся загрузить события для текущего года
        await loadCalendarData(currentYear);
        
        addEventListeners();
        
        // Отрисовываем оба вида
        renderYearView(currentYear); 
        renderLargeMonthView(currentYear, initialDate.getMonth()); // Показываем текущий месяц
        updateYearInfo(currentYear); // Обновляем информацию о годе
    }

    /**
     * Загружает базовые события из events.json (один раз при запуске)
     */
    async function loadBaseEvents() {
        try {
            // Добавляем timestamp и параметры для отключения кеширования
            const response = await fetch(`events.json?t=${Date.now()}`, {
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            if (!response.ok) {
                throw new Error(`Ошибка при загрузке events.json: ${response.status}`);
            }
            baseEvents = await response.json();
            allEvents = [...baseEvents]; // Копируем в allEvents
            console.log('✅ Базовые события загружены из events.json:', baseEvents.length, 'событий');
        } catch (error) {
            console.error('❌ Не удалось загрузить базовые события:', error);
            baseEvents = [];
            allEvents = [];
        }
    }

    /**
     * Загружает данные о событиях из JSON-файла для конкретного года
     * Если файл для года не найден, использует базовые события из events.json
     */
    async function loadCalendarData(year) {
        // Защита от одновременных загрузок
        if (isLoading) {
            console.log('⏳ Загрузка уже выполняется, пропускаю...');
            return;
        }
        
        isLoading = true;
        
        try {
            // Пытаемся загрузить файл для конкретного года
            // Добавляем timestamp и параметры для отключения кеширования
            const response = await fetch(`events_${year}.json?t=${Date.now()}`, {
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });

            if (!response.ok) {
                // Если файл для года не найден, используем базовые события
                console.log(`📄 Файл events_${year}.json не найден, использую базовые события из events.json`);
                allEvents = [...baseEvents]; // Копируем базовые события
                console.log('✅ Используются базовые события:', allEvents.length, 'событий');
                return; // Выходим, чтобы не было лишних try-catch
            }
            
            // Файл для года найден - пытаемся распарсить JSON
            try {
                const data = await response.json();
                
                // Проверяем что данные валидны
                if (!Array.isArray(data)) {
                    throw new Error('Файл не содержит массив событий');
                }
                
                // Если файл пустой, используем базовые события
                if (data.length === 0) {
                    console.log(`⚠️ Файл events_${year}.json пустой, использую базовые события`);
                    allEvents = [...baseEvents];
                    console.log('✅ Используются базовые события:', allEvents.length, 'событий');
                    return;
                }
                
                allEvents = data;
                console.log(`📄 Загружен файл events_${year}.json`);
                console.log('✅ События загружены успешно:', allEvents.length, 'событий');
                
            } catch (parseError) {
                // Ошибка парсинга JSON (файл поврежден или пустой)
                console.error('❌ Ошибка парсинга JSON для года', year, ':', parseError.message);
                console.log('⚠️ Использую базовые события из-за ошибки парсинга');
                allEvents = [...baseEvents];
                console.log('✅ Используются базовые события:', allEvents.length, 'событий');
            }
            
        } catch (error) {
            // Любые другие ошибки (сетевые и т.д.)
            console.error('❌ Ошибка при загрузке событий для года:', error);
            // В случае ошибки используем базовые события
            allEvents = [...baseEvents];
            console.log('⚠️ Используются базовые события из-за ошибки');
        } finally {
            isLoading = false; // Сбрасываем флаг загрузки
        }
    }

    // --- 3. Отрисовка календарей ---

    /**
     * Отрисовывает вид "Год" (12 маленьких месяцев).
     */
    function renderYearView(year) {
        yearView.innerHTML = '';
        
        for (let month = 0; month < 12; month++) {
            const monthEl = document.createElement('div');
            monthEl.className = 'small-month';
            monthEl.dataset.month = month; 

            const title = document.createElement('h3');
            title.textContent = MONTH_NAMES[month];
            monthEl.appendChild(title);

            const grid = document.createElement('div');
            grid.className = 'small-calendar-grid';
            
            WEEK_DAYS.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'small-day-header';
                dayHeader.textContent = day;
                grid.appendChild(dayHeader);
            });

            renderMonthGrid(grid, year, month, 'small');

            monthEl.appendChild(grid);
            yearView.appendChild(monthEl);
        }
    }

    /**
     * Отрисовывает большой вид "Месяц".
     */
    function renderLargeMonthView(year, month) {
        monthNameEl.textContent = `${MONTH_NAMES[month]} ${year}`;
        largeMonthGrid.innerHTML = '';

        WEEK_DAYS.forEach(day => {
            const dayHeader = document.createElement('div');
            dayHeader.className = 'day-header';
            dayHeader.textContent = day;
            largeMonthGrid.appendChild(dayHeader);
        });

        renderMonthGrid(largeMonthGrid, year, month, 'large');
    }

    /**
     * Вспомогательная функция. Генерирует ячейки с днями
     */
    function renderMonthGrid(container, year, month, sizeClass) {
        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let startDayIndex = (firstDayOfMonth.getDay() + 6) % 7;

        // 1. Пустые ячейки
        for (let i = 0; i < startDayIndex; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = `${sizeClass}-day-cell empty`;
            container.appendChild(emptyCell);
        }

        // 2. Ячейки с днями
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.className = `${sizeClass}-day-cell`;
            
            // --- ОБЩАЯ ЛОГИКА для обоих размеров ---
            
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            const dayOfWeek = (startDayIndex + day - 1) % 7;
            if (dayOfWeek === 5 || dayOfWeek === 6) {
                dayCell.classList.add('weekend');
            }

            // Проверяем, является ли день сегодняшним
            const thisDate = new Date(year, month, day);
            const isToday = thisDate.getTime() === today.getTime();
            
            if (isToday) {
                dayCell.classList.add('today');
                console.log('✅ ДОБАВЛЕН КЛАСС .today!', {
                    date: dateStr,
                    year: year,
                    month: month + 1,
                    day: day,
                    sizeClass: sizeClass,
                    classList: dayCell.className,
                    thisDateTimestamp: thisDate.getTime(),
                    todayTimestamp: today.getTime()
                });
            }
            
            // Ищем события на этот день
            // Поддерживаем два формата: "2025-01-01" (с годом) и "01-01" (без года)
            const dateStrShort = `${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const eventsOnThisDay = allEvents.filter(e => 
                e.date === dateStr || e.date === dateStrShort
            );


            // --- Логика для БОЛЬШОГО календаря ---
            if (sizeClass === 'large') {
                const dayNumber = document.createElement('span');
                dayNumber.className = 'day-number';
                dayNumber.textContent = day;
                dayCell.appendChild(dayNumber); // Сначала номер дня

                // Всегда добавляем data-date для возможности клика
                dayCell.dataset.date = dateStr;

                if (eventsOnThisDay.length > 0) {
                    dayCell.classList.add('has-events');

                    // СОРТИРОВКА СОБЫТИЙ ПО ПРИОРИТЕТУ:
                    // 1. События "Весь день"
                    // 2. События с конкретным временем (сортируем по времени)
                    const sortedEvents = eventsOnThisDay.sort((a, b) => {
                        const aIsAllDay = a.time === 'Весь день';
                        const bIsAllDay = b.time === 'Весь день';
                        
                        // "Весь день" идут первыми
                        if (aIsAllDay && !bIsAllDay) return -1;
                        if (!aIsAllDay && bIsAllDay) return 1;
                        
                        // Внутри групп сортируем по времени (если есть)
                        if (a.time && b.time) {
                            return a.time.localeCompare(b.time);
                        }
                        
                        return 0;
                    });

                    // Максимум полосок для отображения
                    const MAX_VISIBLE_EVENTS = 4;
                    const visibleEvents = sortedEvents.slice(0, MAX_VISIBLE_EVENTS);
                    const hiddenEvents = sortedEvents.slice(MAX_VISIBLE_EVENTS);

                    // Создаем контейнер для полосок событий
                    const eventsListEl = document.createElement('div');
                    eventsListEl.className = 'day-events-list';

                    // Отображаем первые N событий полосками
                    visibleEvents.forEach(event => {
                        const eventEl = document.createElement('div');
                        eventEl.className = 'day-event-item';
                        eventEl.style.backgroundColor = event.color || '#4A90E2'; 
                        eventEl.textContent = event.title;
                        eventsListEl.appendChild(eventEl);
                    });

                    dayCell.appendChild(eventsListEl);

                    // Если есть скрытые события, показываем их точками
                    if (hiddenEvents.length > 0) {
                        const dotsContainer = document.createElement('div');
                        dotsContainer.className = 'event-dots-container';
                        
                        hiddenEvents.forEach(event => {
                            const dot = document.createElement('span');
                            dot.className = 'event-dot';
                            dot.style.backgroundColor = event.color || '#4A90E2';
                            dot.title = event.title; // Tooltip при наведении
                            dotsContainer.appendChild(dot);
                        });
                        
                        dayCell.appendChild(dotsContainer);
                    }
                }

            // --- Логика для МАЛЕНЬКОГО календаря ---
            } else {
                dayCell.textContent = day;
                
                // Добавляем класс для индикатора
                if (eventsOnThisDay.length > 0) {
                    dayCell.classList.add('has-events-small');
                }
            }
            
            container.appendChild(dayCell);
        }
    }


    // --- 4. Обработчики событий ---

    function addEventListeners() {
        
        // Кнопки "<" и ">" для смены года
        document.getElementById('prev-year').addEventListener('click', async () => {
            currentYear--;
            yearInput.value = currentYear;
            await loadCalendarData(currentYear); // Загружаем события для нового года
            renderYearView(currentYear);
            renderLargeMonthView(currentYear, 0); // Показываем ЯНВАРЬ
            updateYearInfo(currentYear); // Обновляем информацию о годе
        });

        document.getElementById('next-year').addEventListener('click', async () => {
            currentYear++;
            yearInput.value = currentYear;
            await loadCalendarData(currentYear); // Загружаем события для нового года
            renderYearView(currentYear);
            renderLargeMonthView(currentYear, 0); // Показываем ЯНВАРЬ
            updateYearInfo(currentYear); // Обновляем информацию о годе
        });

        // Ввод года вручную
        yearInput.addEventListener('change', async (e) => {
            currentYear = parseInt(e.target.value, 10);
            await loadCalendarData(currentYear); // Загружаем события для нового года
            renderYearView(currentYear);
            renderLargeMonthView(currentYear, 0); // Показываем ЯНВАРЬ
            updateYearInfo(currentYear); // Обновляем информацию о годе
        });

        // Нажатие на маленький месяц
        yearView.addEventListener('click', (e) => {
            const monthEl = e.target.closest('.small-month');
            if (monthEl) {
                const month = parseInt(monthEl.dataset.month, 10);
                // Просто обновляем большой календарь
                renderLargeMonthView(currentYear, month);
            }
        });

        // Нажатие на день в большом календаре (для модального окна)
        largeMonthGrid.addEventListener('click', (e) => {
            console.log('🖱️ Клик по календарю:', e.target);
            
            // Ищем ближайшую ячейку дня - в большом календаре это .large-day-cell
            const dayCell = e.target.closest('.large-day-cell');
            console.log('📍 Найдена ячейка:', dayCell);
            
            // Открываем модалку для ЛЮБОЙ ячейки (даже пустой)
            if (dayCell) {
                const date = dayCell.dataset.date;
                console.log('📅 Дата:', date);
                if (date) {
                    showEventModal(date);
                } else {
                    console.log('⚠️ У ячейки нет атрибута data-date');
                }
            } else {
                console.log('⚠️ Ячейка не найдена');
            }
        });

        // Закрытие модального окна
        document.getElementById('modal-close-btn').addEventListener('click', hideEventModal);
        modal.addEventListener('click', (e) => {
            // Закрываем по клику на темный фон (overlay)
            if (e.target === modal) {
                hideEventModal();
            }
        });
        
        // --- Обработчики для панели редактирования ---
        
        // Checkbox "Весь день" - включает/выключает поле времени
        editAllDayCheckbox.addEventListener('change', (e) => {
            editTimeInput.disabled = e.target.checked;
            if (e.target.checked) {
                editTimeInput.value = '';
            }
        });
        
        // Кнопка "Отмена" - закрывает панель
        editCancelBtn.addEventListener('click', closeEditPanel);
        
        // Кнопка "Удалить" - удаляет событие
        editDeleteBtn.addEventListener('click', deleteEvent);
        
        // Отправка формы - сохранение изменений
        editForm.addEventListener('submit', saveEventChanges);
        
        // Закрытие панели при клике на overlay (но не при клике внутри панели)
        modal.addEventListener('click', (e) => {
            if (e.target === modal && editPanel.classList.contains('active')) {
                closeEditPanel();
            }
        });
    }

    // --- 5. Модальное окно (логика показа/скрытия) ---

    /**
     * Показывает события для указанной даты (обновленная версия для поддержки редактирования)
     */
    function showEventsForDate(dateStr) {
        console.log('🔔 Обновление списка событий для даты:', dateStr);
        
        // Поддержка обоих форматов: "2025-01-01" и "01-01"
        const dateStrShort = dateStr.split('-').slice(-2).join('-');
        const events = allEvents.filter(e => 
            e.date === dateStr || e.date === dateStrShort
        );
        console.log('📋 Найдено событий:', events.length);
        
        // Очищаем список
        modalEventsList.innerHTML = '';
        
        // ЕСЛИ НЕТ СОБЫТИЙ - показываем сообщение и кнопку добавления
        if (events.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.className = 'empty-day-message';
            emptyMessage.textContent = 'На этот день событий нет';
            modalEventsList.appendChild(emptyMessage);
            
            // Добавляем кнопку "Добавить событие"
            const addButton = document.createElement('button');
            addButton.className = 'add-event-btn';
            addButton.textContent = '➕ Добавить событие';
            addButton.addEventListener('click', () => {
                openEditPanelForNewEvent(dateStr);
            });
            modalEventsList.appendChild(addButton);
            return;
        }
        
        // Добавляем события в список С КНОПКАМИ РЕДАКТИРОВАНИЯ
        events.forEach((event, localIndex) => {
            // Находим глобальный индекс события в массиве allEvents
            const globalIndex = allEvents.findIndex(e => 
                e === event || (e.date === event.date && e.title === event.title && e.time === event.time)
            );
            
            const item = document.createElement('div');
            item.className = 'event-item';
            
            item.innerHTML = `
                <div class="event-item-color" style="background-color: ${event.color};"></div>
                <div class="event-item-details">
                    <div class="event-item-title">${event.title}</div>
                    <div class="event-item-time">${event.time}</div>
                </div>
                <button class="edit-event-btn" data-event-index="${globalIndex}" title="Редактировать">✏️</button>
            `;
            
            modalEventsList.appendChild(item);
        });
        
        // Добавляем кнопку "Добавить ещё событие" внизу списка
        const addButton = document.createElement('button');
        addButton.className = 'add-event-btn';
        addButton.textContent = '➕ Добавить событие';
        addButton.addEventListener('click', () => {
            openEditPanelForNewEvent(dateStr);
        });
        modalEventsList.appendChild(addButton);
        
        // Добавляем обработчики для кнопок редактирования
        const editButtons = modalEventsList.querySelectorAll('.edit-event-btn');
        editButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Предотвращаем всплытие события
                const eventIndex = parseInt(btn.dataset.eventIndex);
                const event = allEvents[eventIndex];
                openEditPanel(eventIndex, event);
            });
        });
    }
    
    /**
     * Открывает панель редактирования для создания нового события
     */
    function openEditPanelForNewEvent(dateStr) {
        console.log('➕ Открываю панель для создания нового события на дату:', dateStr);
        
        editingEventIndex = -1; // Новое событие
        editingEventDate = dateStr; // Сохраняем дату в переменной
        
        // Очищаем форму
        editForm.reset();
        
        // edit-date больше нет, дата хранится в переменной
        
        // Устанавливаем цвет по умолчанию (первый)
        const firstColorInput = document.querySelector('input[name="color"]');
        if (firstColorInput) {
            firstColorInput.checked = true;
        }
        
        // Показываем панель
        editPanel.classList.add('active');
        
        // Скрываем кнопку удаления для нового события
        editDeleteBtn.style.display = 'none';
    }

    function showEventModal(dateStr) {
        console.log('🔔 Вызов showEventModal для даты:', dateStr);
        
        // Форматируем дату для заголовка
        const [y, m, d] = dateStr.split('-');
        const date = new Date(y, m - 1, d);
        modalDateEl.textContent = date.toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        
        // Показываем события
        showEventsForDate(dateStr);
        
        console.log('✅ Открываю модальное окно');
        modal.classList.add('active'); // Используем active вместо remove hidden
        console.log('📊 Классы модального окна:', modal.className);
    }

    /**
     * Скрывает модальное окно.
     */
    function hideEventModal() {
        modal.classList.remove('active'); // Используем remove active вместо add hidden
        // Закрываем панель редактирования, если она открыта
        if (editPanel.classList.contains('active')) {
            closeEditPanel();
        }
    }
    
    // --- 6. Запуск! ---
    init();

});