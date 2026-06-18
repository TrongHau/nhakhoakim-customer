# Customer

## Khởi tạo dự án
```
docker build -t tinnguyennct/nginx-php7.2:v6 .
```

### Lần đầu tiên
Cài đặt docker tại https://www.docker.com/products/docker-desktop/
Tại vị trí source code, thực hiện lệnh trong terminal / console
Copy các file app.config.php và defines.php vào các folder tương ứng. 
Liên hệ team DevOps để lấy file
```
chmod 700 cli
./cli docker start
./cli init
```
API chạy theo đường dẫn http://127.0.0.1:6791

### Từ lần 2 trở đi
Tại vị trí source code
```
./cli docker start
```
API chạy theo đường dẫn http://127.0.0.1:6791

Health check http://127.0.0.1:6791/ping

### Tạo model tự động

Với schema pos

```
php artisan generate:modelfromtable --connection=mysql --singular --table=Appointment
```

Với schema in

```
php artisan generate:modelfromtable --connection=mysql_in --singular --table=Staff
```

Với schema ai

```
php artisan generate:modelfromtable --connection=mysql_ai --singular --table=ConsultationTraining
```