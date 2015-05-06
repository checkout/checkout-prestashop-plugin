echo 'modman init start'
call modman init
echo 'modman init end'
PAUSE
echo 'modman link start'
call modman link %cd%\..\..\..\Core/PHP --force
echo 'modman link end'