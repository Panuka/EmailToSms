Бизнес логика сервиса:
=========================
На сервере в кроне запускается каждую секунду метод exec класса console. В этом методе получаются и последовательно обрабатываются пользователи.
Обработка пользователя состоит из следующих этапов:
Инициализация пользователя в системе, обработка, заверешние обработки.
Стартовые и финишные операции связаны с предвадительно обработкой входных данных из БД, записью логов работы и тд.
Обработка состоит из получения данных из почты, составления писем, отправки, записи информации об отправленных письмах, а также рассылки писем по расписанию.
Особенности работы:
- Таблица с письмами при каждой загрузке конвертируется в массив и сохраняется в бд в сериализованном виде. В случае, если сериализованных данных нет, считывается файл, указанный в соответсвующем поле бд.
- Получение смс-сервисов реализовано через абстрактную фабрику, подключение новых по сути нуждается в создании публичного метода sendSMS
- На протяжении всей работы класса доступен метод toLog, который сохранит данные для вывода.
- Web и Console расположены на одном ядре.

Класс Console
=========================
xls - файл с таблицей
car_to_sms - предложения с машинами, которые необхолимо отправить
msg - массив с сообщениями и номерами, куда отправлять
current_mail - ID текущего обрабатываемого письма
user - объект пользователя
api_private - приватный ключ api
api_public - публичный ключ api
web - флаг использования метода вне консоли
URL_GAREWAY - адрес api
__construct - иницаилазция класса
prepareUser - обработка модели пользователя
exec - исполнение бизнес логики
processUser - обработка пользователя, получение данных
getUsers - получение всех пользователей
prepareOffers - подготовка офферов
toLog - записать в лог файл
writeStats - записать статистику по итерации
loadCars - прогрузка машин
sendMsg - отправить сообщение
sendMessages - разослать сообщения 
getTableOffers - прочитать таблицу машин
checkMail - проверить почту
processFromCsv - обработка csv данных
processFromBot - обработка с парсера
convertToArray - xls->array
normalizeModel - нормализовать название машины
addCarToSend - обавить машину к отправки 
inarr - находится ли строка в массиве
reportSelectQuery - собрать данные по юзеру для статитстики
sendReport - отправить сообщение со статистикой на почту
createMail - создать базовое письмо
checkBalance - провериться баланс
minBalance - сообщение о мин. балансе
createCsvReport - создать csv отчет по пользователю
getGeoFromNumber - получение гео по номеру
cantDeterminateRegion - обработка исключения, когда не удалось определить регион
smsDown - обработка исключения, когда упал смс
emailStatus - текущий статус сообщения
END_ACTIVITY - действия после обработки пользователя
START_ACTIVITY - начало обработки пользователя
getSms - фабрика получения апи для работы с смс
deal - составить предложение
getMsg - создать сообщение
getOffer - извлечь предложение
report - отчет