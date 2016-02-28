<?php
set_time_limit(0);

require_once 'funciones.php';
require_once 'conexion.php';

empezar:

backupdb();

unlink('/var/www/html/dev/curl.txt');	//BORRAMOS LA CUENTA DE LAS CONEXIONES DE CURL

$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM COLA'); 
$row = mysqli_fetch_assoc($result);
$cola = $row['COUNT(*)'];

if($cola>200)
	goto procesar_cola;

$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM LIKES'); 
$row = mysqli_fetch_assoc($result);
$likes = $row['COUNT(*)'];

while($likes>0){			//Si hay algo en la tabla likes hay que procesarlo y llevar los perfiles a la cola antes de nada.

	archivo('Habia algo en likes...procesando...'.date("H:i:s"));

	extraerperfiles();

	$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM LIKES'); 
	$row = mysqli_fetch_assoc($result);
	$likes = $row['COUNT(*)'];

	if($likes==0){
		archivo('Likes primitivos procesados, durmiendo 60seg.'.date("H:i:s"));
		sleep(60);
	}

}
archivo('Eligiendo popu...'.date("H:i:s"));

$popus = array('Josillooo','albertcrezzio','mariiagomezrispa','albeertooalcaide3','LaRaydelacoma','oriolcastellss97','Morenys4','DaniSan16','PabloLeraLoL');

elegir:

$popu = $popus[array_rand($popus)];

if(!megusta($popu))
	goto elegir;	//SI EL PERFIL ESTA DESACTIVADO O BANEADO NO TENDRA LIKES/MEGUSTA CON LO CUAL ELEGIMOS OTRO

$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM LIKES'); 
$row = mysqli_fetch_assoc($result);
$likes = $row['COUNT(*)'];

sleep(5);

while($likes>0){			//Si hay algo en la tabla likes hay que procesarlo y llevar los perfiles a la cola.

	archivo('Procesando likes extraidos...'.date("H:i:s"));

	extraerperfiles();

	$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM LIKES'); 
	$row = mysqli_fetch_assoc($result);
	$likes = $row['COUNT(*)'];
}
archivo('Likes propios procesados, durmiendo 150 seg.'.date("H:i:s"));
sleep(150);

$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM COLA'); 
$row = mysqli_fetch_assoc($result);
$cola = $row['COUNT(*)'];

procesar_cola:

$contador = 0;

while($cola>0){

	analizar();	//ENTRA EN LA COLA Y SACA UN PERFIL PARA ANALIZAR

	$result = mysqli_query($conexion, 'SELECT COUNT(*) FROM COLA'); 
	$row = mysqli_fetch_assoc($result);
	$cola = $row['COUNT(*)'];

	if($contador>1000){
		backupdb();
		$contador = 0;
	}else
		++$contador;
}

archivo('Creando copia de seguridad...'.date("H:i:s"));
backupdb();

archivo('Cola procesada, durmiendo 300 seg.'.date("H:i:s"));
sleep(300);
goto empezar;

?>