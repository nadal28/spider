<?php

function curl($url)
{
	sleep(mt_rand(1,2));

	$urlyql = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20html%20where%20url%3D%27'.$url.'%27';
	$url = array($url,$urlyql);

	$url = $url[array_rand($url)];	//SELECCIONAMOS AL AZAR ENTRE MI IP O LA DE YAHOO PARA CRAWLEAR, MAS VELOCIDAD PARA LA ARAÑA.

	//================================AGENT SPOOFING======================================
	$agent = array();	//	$agent[] = ''; 

	$agent[] = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1';
	$agent[] = 'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0'; 
	$agent[] = 'Googlebot/2.1 (+http://www.google.com/bot.html)'; 
	$agent[] = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36'; 
	$agent[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.1 Safari/537.36'; 
	$agent[] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36'; 
	$agent[] = 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36';
	$agent[] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A';
	$agent[] = 'Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16';
	$agent[] = 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; fr) Presto/2.9.168 Version/11.52';
	$agent[] = 'Mozilla/5.0 (Windows NT 5.1; U; en; rv:1.8.1) Gecko/20061208 Firefox/5.0 Opera 11.11'; 
 

	$agent = $agent[array_rand($agent)]; 

	$ch = curl_init();

  	curl_setopt($ch, CURLOPT_URL, $url);
  	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	
	$response = curl_exec($ch);
	curl_close($ch);

	//=========GUARDAMOS EL NUMERO DE CONEXIONES ACTUAL=============
	$fp = fopen('/var/www/html/dev/curl.txt', 'r');
	$contenido = (int)stream_get_contents($fp);
	$contenido++;

	$fp = fopen('/var/www/html/dev/curl.txt', 'w+');
	fwrite($fp, $contenido);
	fclose($fp);
	//==============================================================

	return $response;
}

function megusta($nick)	//Le pasan un usuario EJ:'albertcrezzio' y almacena las primeras direcciones 'answers/likes'. ESTO ES SOLO AL PRINCIPIO, PARA POPUS.
{	
	require('conexion.php');

	$url = 'https://ask.fm/'.$nick;
	$pagina = curl($url);
	$patron = '/(?<=GMT"\shref="\/'.$nick.'\/answers\/)\d+/i';

	if(!preg_match_all($patron, $pagina, $urls))	//Si es un perfil sin likes finaliza, parametros invalidos etc..
		return false;

	unset($pagina,$patron);

	foreach ($urls[0] as $url)
	{	
        mysqli_query($conexion, "INSERT INTO LIKES(url, page, nick) VALUES('$url','0','$nick')");
    }
	return true;
}

function extraerperfiles()
{
	require('conexion.php');

	$result = mysqli_query($conexion, 'SELECT nick,url,page FROM LIKES ORDER BY id DESC LIMIT 1'); //SACAMOS LA PREGUNTA DONDE ESTAN TODOS LOS LIKES, DESC PORQUE LOS LIKES DE ABAJO CONTIENEN MAS PERFILES Y LOS DE ARRIBA SE VAN LLENANDO.
	$row = mysqli_fetch_assoc($result);
	$nick = $row['nick'];
	$url = $row['url'];
	$page = $row['page'];

	mysqli_free_result($result);

	$newurl = 'https://ask.fm/'.$nick.'/answers/'.$url.'/likes/more?page='.$page;

	$pagina = curl($newurl);

	preg_match_all('/(?<=@)\w+/m', $pagina, $perfiles);

	unset($pagina,$newurl,$result,$row,$page);

	$cuenta = 0; // CADA 'page=X' IMPRIME COMO MUCHO 25 PERFILES, SI HAY MENOS, QUIERE DECIR QUE ESTAMOS EN LA ULTIMA PAGE DE ESE REGISTRO POR LO TANTO BORRAMOS.

	foreach ($perfiles[0] as $nick)
	{
		cola_nuevo($nick);
    	++$cuenta;
    }	    	

	if($cuenta == 25){
		$sql = "UPDATE LIKES SET page = page + 1 WHERE url='$url'";
		mysqli_query($conexion, $sql);
	}else{
		$sql = "DELETE FROM LIKES WHERE url = '$url'";
		mysqli_query($conexion, $sql);
	}

	mysqli_free_result($result);	
}		


function analizar(){

	require('conexion.php');	
	mysqli_set_charset($conexion, 'utf8mb4');

	$ahora = time();
	$ahora2 = date('Y-m-d H:i:s');

	$result = mysqli_query($conexion, 'SELECT nick FROM COLA ORDER BY tocado DESC,id LIMIT 1');	//El perfil con mas prioridad en cola siempre sera el que mas tocado este y con id mas bajo
	$row = mysqli_fetch_assoc($result);
	$nick = $row['nick'];

	mysqli_free_result($result);

	archivo('Analizando: '.$nick.' '.date("H:i:s"));

	$result = mysqli_query($conexion, "SELECT baneado,lastseen FROM PERFILES WHERE nick='$nick'"); 
	if(mysqli_num_rows($result)==1){															//PARA SABER SI ESTA EN LA DB O ES NUEVO
		$row = mysqli_fetch_assoc($result);
		$lastseen = strtotime($row['lastseen']);
		$baneado = $row['baneado'];
	}

	
	if(mysqli_num_rows($result)==0){	//Si el usuario no estaba indexado	https://ask.fm/antonia_velilla/answers/more?page=0

		$url = 'https://ask.fm/'.$nick;
		$pagina = curl($url);

		if(preg_match('/(http-status-code="403")|(<p>Account Disabled<\/p>)|(<h1>This account has been suspended.<\/h1>)/i', $pagina)){		//Si el usuario ha sido baneado o tiene la cuenta desactivada
			mysqli_query($conexion,"DELETE FROM COLA WHERE nick='$nick'");
			mysqli_query($conexion,"DELETE FROM PERFILES WHERE nick='$nick'");
			return;
		}

		if(preg_match('/<div class="profileTabAnswerCount">0<\/div>/i', $pagina)){	//Si el usuario tiene 0 respuestas
			mysqli_query($conexion,"DELETE FROM COLA WHERE nick='$nick'");
			mysqli_query($conexion,"DELETE FROM PERFILES WHERE nick='$nick'");
			return;
		}
	
		preg_match('/(?<=data-hint=")(.*?)(?=")/i',$pagina,$fecha); //Si han pasado mas de dos semanas desde la ultima respuesta
		$fecha = strtotime($fecha[0]);

		$diferencia = time() - $fecha;

		if($diferencia>1209600){	//1209600=2semanas
			mysqli_query($conexion,"DELETE FROM COLA WHERE nick='$nick'");
			mysqli_query($conexion,"DELETE FROM PERFILES WHERE nick='$nick'");
			return;
		}

		$bio = htmlspecialchars_decode(bio($pagina));
		$bio = addslashes(htmlspecialchars_decode($bio,ENT_QUOTES));

		$nombre = htmlspecialchars_decode(nombre($pagina));
		$nombre = addslashes(htmlspecialchars_decode($nombre,ENT_QUOTES));

		$likes = likes($pagina);
		$avatar = avatar($pagina);

		$sql = "INSERT INTO PERFILES(nick,nombre,likes,avatar,bio,firstseen,lastseen,profundidad,baneado) VALUES('$nick','$nombre','$likes','$avatar','$bio','$ahora2','$ahora2','0','0')";
		mysqli_query($conexion, $sql);

		archivo('Mirando respuestas de '.$nick.' '.date("H:i:s"));

		$page=0;
		while($page<4){
			if(respuestas(curl($url."/answers/more?page=$page")))
				++$page;
			else
				$page=4;
		}

	}
	else{	//Si esta indexado

		if($baneado){
			mysqli_query($conexion,"DELETE FROM COLA WHERE nick='$nick'");
			return;
		}

		$diferencia = $ahora - $lastseen;

		if($diferencia>604800 || $baneado===NULL){			//Si ha pasado una semana actualizamos el perfil, si no lo borramos de la cola
			
			$url = 'https://ask.fm/'.$nick;
			$pagina = curl($url);

			$bio = htmlspecialchars_decode(bio($pagina));
			$bio = addslashes(htmlspecialchars_decode($bio,ENT_QUOTES));

			$nombre = htmlspecialchars_decode(nombre($pagina));
			$nombre = addslashes(htmlspecialchars_decode($nombre,ENT_QUOTES));
			
			$likes = likes($pagina);
			$avatar = avatar($pagina);

			if($baneado!==NULL){	

				if($bio){
					$sql = "UPDATE PERFILES SET bio='$bio',nombre='$nombre',lastseen='$ahora2',likes='$likes',avatar='$avatar',baneado='0' WHERE nick='$nick'";
					mysqli_query($conexion, $sql);
				}else{
					$sql = "UPDATE PERFILES SET nombre='$nombre', lastseen='$ahora2',likes='$likes',avatar='$avatar',baneado='0' WHERE nick='$nick'";
					mysqli_query($conexion, $sql);
				}
			}else{	//Si el perfil esta en la tabla pero no ha sido analizado
				if($bio){
					$sql = "UPDATE PERFILES SET bio='$bio',nombre='$nombre',firstseen='$ahora2',lastseen='$ahora2',likes='$likes',avatar='$avatar',baneado='0' WHERE nick='$nick'";
					mysqli_query($conexion, $sql);
				}else{
					$sql = "UPDATE PERFILES SET nombre='$nombre', firstseen='$ahora2', lastseen='$ahora2',likes='$likes',avatar='$avatar',baneado='0' WHERE nick='$nick'";
					mysqli_query($conexion, $sql);
				}
			}

			$result = mysqli_query($conexion, "SELECT profundidad FROM PERFILES WHERE nick='$nick'");
			$row = mysqli_fetch_assoc($result);
			$profundidad = $row['profundidad'];	//Solo sacamos respuestas de los perfiles con profundidad 0 para evitar otros paises

			archivo('Mirando respuestas de '.$nick.' '.date("H:i:s"));

			if($profundidad==0){
				$page=0;
				while($page<4){
					if(respuestas(curl($url."/answers/more?page=$page")))
						++$page;
					else
						$page=4;
				}
			}
		}
	}

	mysqli_free_result($result);


	$sql = "DELETE FROM COLA WHERE nick='$nick'";
	mysqli_query($conexion, $sql);

	mysqli_close($conexion);
}

function creartablas(){
	require 'conexion.php';

	mysqli_query($conexion,'CREATE TABLE LIKES(
		id int auto_increment primary key not null,
		url bigint unsigned not null,
		page tinyint unsigned not null,
		nick varchar(40) not null)
		ENGINE=InnoDB');

	mysqli_query($conexion,'CREATE TABLE COLA(
		id int auto_increment not null unique,
		nick varchar(40) not null primary key,
		tocado tinyint unsigned not null)
		ENGINE=InnoDB');

	mysqli_query($conexion,'CREATE TABLE PERFILES(
		id int auto_increment not null unique,
		nick varchar(40) not null primary key,
		nombre varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
		likes int unsigned,
		avatar varchar(150),
		firstseen datetime,
		lastseen datetime,
		bio text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
		baneado tinyint(1),
		profundidad tinyint(1) not null,
		checked TINYINT(1) NOT NULL DEFAULT "0")
		ENGINE=InnoDB');

	mysqli_query($conexion,'CREATE TABLE RESPUESTAS(
		id int auto_increment not null primary key,
		nick varchar(40) not null,
		url bigint unsigned not null,
		asker varchar(40),
		likes int unsigned not null,
		media tinyint(1) not null,
		fecha datetime not null,
		pregunta text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin not null,
		respuesta text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin not null,
		FOREIGN KEY (nick) REFERENCES PERFILES(nick)
			ON DELETE CASCADE,
		FOREIGN KEY (asker) REFERENCES PERFILES(nick)
			ON DELETE CASCADE)
		ENGINE=InnoDB');

	mysqli_query($conexion,'CREATE TABLE RESPUESTAS_LIKES(
		id bigint auto_increment not null primary key,
		id_respuesta int not null,
		nick varchar(40) not null,
		FOREIGN KEY (nick) REFERENCES PERFILES(nick)
			ON DELETE CASCADE,
		FOREIGN KEY (id_respuesta) REFERENCES RESPUESTAS(id)
			ON DELETE CASCADE)
		ENGINE=InnoDB');
}
/*
function borrartablas(){

	require('conexion.php');

	$sql = 'DROP TABLE RESPUESTAS_LIKES';
	mysqli_query($conexion, $sql);

	$sql = 'DROP TABLE RESPUESTAS';
	mysqli_query($conexion, $sql);

	$sql = 'DROP TABLE PERFILES';
	mysqli_query($conexion, $sql);

	$sql = 'DROP TABLE COLA';
	mysqli_query($conexion, $sql);

	$sql = 'DROP TABLE LIKES';
	mysqli_query($conexion, $sql);
}
*/
function clean($string){

$string = strip_tags($string);
$patrones = array('/▬+/','/\s+/');
$sustituciones = array(' ',' ');
$string = preg_replace($patrones, $sustituciones, $string);
//$string = substr($string, 1);

return $string;

}

function avatar($pagina){
	if(preg_match_all('/(?<=background-image:url\().+thumb(.*?)(?=\))/i', $pagina, $avatar))
		$avatar = $avatar[0][0];
	else
		$avatar = 'http://i.imgur.com/9fdDQjF.png';
	return $avatar;
}

function nombre($pagina){
	preg_match_all('/(?<=<span class="blockLink head bold">)(.*?)(?=<\/span>)/i', $pagina, $nombre);
	$nombre = strip_tags($nombre[0][0]);	//strip_tags porque los nombres que empiezan por caracteres arabes les añaden la etiqueta	<span dir="rtl">
	return $nombre;
}

function likes($pagina){
	preg_match('/<div class="profileTabLikeCount">.+/i', $pagina, $bloquelikes);
	preg_match('/(?<=>)\d+(?=<)/', $bloquelikes[0],$likes);
	return $likes[0];
}

function bio($pagina){
	if(!preg_match('/(?<=<div class="aboutMore more">\n)([\s\S]*?)(?=<a class="moreButton")/i', $pagina, $bio))	//Si el perfil no tiene biografia almacena null.
		$bio = null;
	else{
		$sustituciones = array('<br />','<br>','<br/>');
		$bio = strip_tags(str_replace($sustituciones,' ',$bio[0]));
		$bio = substr(preg_replace('/\s+/',' ',$bio),1,-1);
	}
	return $bio;
}

function archivo($str){
	$fp = fopen('/var/www/html/dev/data.txt', 'w');
	fwrite($fp, $str);
	fclose($fp);
}

function respuestas($pagina){
	require 'conexion.php';
	mysqli_set_charset($conexion, 'utf8mb4');

	//===============================OBTENCION DE DATOS DE RESPUESTAS==============================================

	if(!preg_match_all('/<h1 class="streamItemContent streamItemContent-question">([\s\S]*?)<a class="streamItemOptions-share icon-share".+/i', $pagina, $respuestas))
		return false;

	foreach($respuestas[0] as $respuestaraw){
		preg_match_all('/(?<=data-hint=")(.*?)(?=")/i',$respuestaraw,$fecha);
		$fecha = date('Y-m-d H:i:s',strtotime($fecha[0][0]));						//FECHA RESPUESTA

		preg_match('/<a class="streamItemsAge".+/i', $respuestaraw, $bloque);
		preg_match('/(?<=\/answers\/)\d+/', $bloque[0],$url);
		$url = $url[0];																//URL RESPUESTA

		preg_match("/\w+(?=\/answers\/)/i", $bloque[0], $nick);
		$nick = $nick[0];															//OWNER RESPUESTA

		if(preg_match('/<a class="counter".+/', $respuestaraw, $likes)){
			preg_match('/(?<=>)\d+(?=<)/', $likes[0],$likes);						//LIKES RESPUESTA
			$likes = $likes[0];
		}else
			$likes = 0;


		preg_match('/(?<=<h1 class="streamItemContent streamItemContent-question">)([\s\S]*?)((?=<a class="questionersName")|(?=<\/h1>))/i', $respuestaraw,$pregunta);
		$pregunta = clean($pregunta[0]);
		$pregunta = htmlspecialchars_decode($pregunta);
		$pregunta = addslashes(htmlspecialchars_decode($pregunta,ENT_QUOTES)); 

		preg_match('/(?<=<p class="streamItemContent streamItemContent-answer">)([\s\S]*?)(?=<\/p>)/i', $respuestaraw,$respuesta);
		$respuesta = clean($respuesta[0]);
		$respuesta = htmlspecialchars_decode($respuesta);
		$respuesta = addslashes(htmlspecialchars_decode($respuesta,ENT_QUOTES));


		if(preg_match('/(?<=<a class="questionersName"\shref="\/)\w+/i', $respuestaraw, $asker))
			$asker = $asker[0];

		if(preg_match('/streamItemContent streamItemContent-visual/i',$respuestaraw))
			$media = 1;
		else
			$media = 0;

		//================================ALMACENAMIENTO EN LA DB==============================================================================

		$result = mysqli_query($conexion, "SELECT COUNT(*) FROM RESPUESTAS WHERE nick='$nick' and url='$url'"); //COMPROBAMOS SI LA RESPUESTA YA ESTA GUARDADA EN LA DB
		$row = mysqli_fetch_assoc($result);
		$cuenta = $row['COUNT(*)'];

		mysqli_free_result($result);
		

		if($cuenta==0){

			if(!empty($asker)){
				cola_nuevo($asker);
				mysqli_query($conexion,"INSERT INTO PERFILES(nick,profundidad) VALUES('$asker','1')");
				mysqli_query($conexion,"INSERT INTO RESPUESTAS(fecha,nick,url,likes,asker,media,pregunta,respuesta) VALUES('$fecha','$nick','$url','$likes','$asker','$media','$pregunta','$respuesta')");	
			}else
				mysqli_query($conexion,"INSERT INTO RESPUESTAS(fecha,nick,url,likes,asker,media,pregunta,respuesta) VALUES('$fecha','$nick','$url','$likes',NULL,'$media','$pregunta','$respuesta')");

		$id_respuesta = mysqli_insert_id($conexion);

		}else{
			mysqli_query($conexion,"UPDATE RESPUESTAS SET likes=$likes WHERE nick='$nick' and url='$url'");
			$result = mysqli_query($conexion,"SELECT id FROM RESPUESTAS WHERE nick='$nick' and url='$url'");
			$row = mysqli_fetch_assoc($result);
			$id_respuesta = $row['id'];
		}

		respuestas_likes($id_respuesta,$nick,$url);	
	}
	return true;
}

function backupdb(){

//=====================ELIMINO BACKUPS ANTIGUOS MENOS EL MAS MODERNO========================
	chdir('/home/adri/backups');
	$backupsraw = shell_exec('ls');	//Listo los backups actuales

	preg_match_all('/(.*?)\.sql/i', $backupsraw, $backupsraw);	//Los guardo en el array backupsraw

	$backups = array();

	foreach($backupsraw[0] as $backup){
		$backup = strtotime(str_replace('.sql', '', $backup));	//Sacamos la fecha en unix para hacer la comparacion
		$backups[] = $backup;
	}

	unset($backups[array_search(max($backups), $backups)]);	//Elimino del array el backup mas nuevo.

	$comando = 'rm ';							//Construyo el comando
	foreach($backups as $backup){
		$backup = '"'.date('Y-m-d H:i:s',$backup).'"';
		$comando.= "$backup.sql ";
	}
	$comando = substr($comando, 0,-1);	//Elimino el ultimo whitespace del comando

	exec($comando);

	//===========HAGO EL BACKUP======================
	$fecha = date('Y-m-d H:i:s');
	exec('mysqldump -u***** -p***** --single-transaction ask > "/home/adri/backups/'.$fecha.'.sql"');
	
}

function respuestas_likes($id_respuesta,$nick,$url){
	require 'conexion.php';

	$page = 0;

	while(1){

		$pagina = curl("https://ask.fm/$nick/answers/$url/likes/more?page=$page");
		preg_match_all('/(?<=@)\w+/m', $pagina, $nicks);

		foreach($nicks[0] as $nick){
			$repetidos = 0;	//Si hay muchos repetidos significa que ya no hay que recorrer mas la respuesta puesto que ya esta actualizada

			$result = mysqli_query($conexion,"SELECT * FROM RESPUESTAS_LIKES WHERE nick='$nick' and id_respuesta='$id_respuesta'");	//Evito guardar likes repetidos sobre una misma respuesta
			if(mysqli_num_rows($result) == 0){
				cola_nuevo($nick);
				mysqli_query($conexion,"INSERT INTO PERFILES(nick,profundidad) VALUES('$nick','1')");
				mysqli_query($conexion,"INSERT INTO RESPUESTAS_LIKES(id_respuesta,nick) VALUES('$id_respuesta','$nick')");
			}
			elseif($repetidos>25)	//Si ya estaba indexado
				return;
			else
				++$repetidos;
		}
		if(count($nicks[0]) == 25)	//Si la pagina nos devolvio 25 nicks quiere decir que hay mas y que podemos avanzar de pagina ya que 25 es el tope por pagina.
			++$page;
		else
			return;
	}
}

function cola_nuevo($nick){
	require 'conexion.php';

	$result = mysqli_query($conexion,"SELECT nick FROM COLA WHERE nick='$nick'");	//PARA COMPROBAR SI YA ESTA EN COLA
	if(mysqli_num_rows($result) == 0)
		mysqli_query($conexion,"INSERT INTO COLA(nick,tocado) VALUES('$nick','0')");
	else
		mysqli_query($conexion,"UPDATE COLA SET tocado = tocado + 1 WHERE nick='$nick'");
}
?>