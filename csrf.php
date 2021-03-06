<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * @package     CSRF
 * @copyright   2020 Podvirnyy Nikita (Observer KRypt0n_)
 * @license     GNU GPLv3 <https://www.gnu.org/licenses/gpl-3.0.html>
 * @author      Podvirnyy Nikita (Observer KRypt0n_)
 * 
 * Contacts:
 *
 * Email: <suimin.tu.mu.ga.mi@gmail.com>
 * VK:    <https://vk.com/technomindlp>
 *        <https://vk.com/hphp_convertation>
 * 
 */

# Ключ шифрование cookie с CSRF токеном
const CSRF_ENCRYPTION_KEY = '(*#@($OPFEJDFLK#J$OEFL:#@$(Q@){$V*(%)(@*$V ^&)(QW$@)$MVYN(@*( WEHF GUEKIFiueI(*UC)$U(*Y$($OQ$X@*&Q@Y$C@Q*(VT*HAEIUG^&@UQD';

# Удалять ли CSRF токен после успешной верификации
# Необходимо для предотвращения повторной эксплуатации токенов
const CSRF_REMOVE_AFTER_VERIFYING = true;

function csrf ($lifetime = null)
{
    if (session_status () !== PHP_SESSION_ACTIVE)
        session_start ();

    $ip = !empty ($_SERVER['HTTP_CLIENT_IP']) ?
        $_SERVER['HTTP_CLIENT_IP'] : (
            !empty ($_SERVER['HTTP_X_FORWARDED_FOR']) ?
                $_SERVER['HTTP_X_FORWARDED_FOR'] :
                $_SERVER['REMOTE_ADDR']);

    $userAgent = $_SERVER['HTTP_USER_AGENT'];

    # Если в функцию передана строка - проверяем её в качестве токена
    if (is_string ($lifetime))
    {
        # Проверяем токен и если он корректен - удаляем его
        # чтобы в следующий раз создался новый
        if (($status = $lifetime == csrf(0)) && CSRF_REMOVE_AFTER_VERIFYING)
        {
            unset ($_SESSION['csrf']);
            setcookie ('csrf', '', time () - 3600);
        }

        return $status;
    }

    # Если передано число или null - создаём новый токен
    elseif (is_int ($lifetime) || $lifetime === null)
    {
        # Если CSRF токен сохранён в сессии
        if (isset ($_SESSION['csrf']))
        {
            # Получаем токен из сессии
            $csrf = $_SESSION['csrf'];

            # Проверяем его на активность и если что - удаляем
            if (($csrf['timestamp'] !== null && $csrf['timestamp'] < time ()) || $csrf['ip'] != $ip || $csrf['user_agent'] != $userAgent)
            {
                unset ($_SESSION['csrf']);

                return csrf ($lifetime);
            }
        }

        # Если CSRF токен сохранён в cookie сайта
        elseif (isset ($_COOKIE['csrf']))
        {
            # Получаем токен из cookie
            $csrf = $_COOKIE['csrf'] ^ str_repeat (CSRF_ENCRYPTION_KEY, ceil (strlen ($_COOKIE['csrf']) / strlen (CSRF_ENCRYPTION_KEY)));
            $csrf = @unserialize ($csrf);

            # Если токен неправильно расшифровался или был неактивен - удаляем его
            if (!is_array ($csrf) || ($csrf['timestamp'] !== null && $csrf['timestamp'] < time ()) || $csrf['ip'] != $ip || $csrf['user_agent'] != $userAgent)
            {
                setcookie ('csrf', '', time () - 3600);

                return csrf ($lifetime);
            }

            # Сохраняем токен в сессию чтобы было проще с ним работать
            $_SESSION['csrf'] = $csrf;
        }

        # Если CSRF токен нигде не сохранён - создаём новый
        else
        {
            # Массив конфигураций токена
            $csrf = array (
                # Сам токен, генерируемый случайным образом
                'token' => hash ('sha256', uniqid (
                    extension_loaded ('openssl') ?
                        openssl_random_pseudo_bytes (32) : rand (PHP_INT_MIN, PHP_INT_MAX), true)),
                
                # timestamp, до которой будет жить токен
                # если $lifetime == null - токен будет активным до конца сессии и рестарта браузера
                'timestamp' => $lifetime !== null ?
                    time () + (int) $lifetime : null,

                # IP клиента, которому выдан CSRF токен
                'ip' => $ip,

                # user agent клиента
                'user_agent' => $userAgent
            );

            # Шифруем информацию о токене и сохраняем их в сессию и cookie
            $csrf_encrypted  = serialize ($csrf);
            $csrf_encrypted ^= str_repeat (CSRF_ENCRYPTION_KEY, ceil (strlen ($csrf_encrypted) / strlen (CSRF_ENCRYPTION_KEY)));

            setcookie ('csrf', $csrf_encrypted, $csrf['timestamp'] !== null ?
                $csrf['timestamp'] : 0);

            $_SESSION['csrf'] = $csrf;
        }

        # Возвращаем токен
        return $csrf['token'];
    }

    # Если в функцию передано не число и не строка - выводим ошибку
    else throw new Exception ('Incorrect $lifetime property value');
}
