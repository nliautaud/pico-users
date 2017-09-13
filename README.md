# Pico Users

A hierarchical users and rights system plugin for [Pico CMS](http://pico.dev7studios.com). There is a port for [Phile CMS](https://github.com/PhileCMS/Phile) by [pschmitt](https://github.com/pschmitt) : [phileUsers](https://github.com/pschmitt/phileUsers).

Features login and logout system, unlimited users and hierarchical user groups, simple rights management.

* [Installation](#installation)
* [Settings](#users-and-groups)
  * [Users and groups](#users-and-groups)
  * [Rights](#rights)
* [Login and logout](#login-and-logout)
* [Error page](#error-page)


## Installation

Copy `PicoUsers.php` to the `plugins/` directory of your Pico Project.

## Settings

Users, rights and others settings should be stored in Pico `config/config.php` file.

```php
$config['users'] = array(
    'family' => array(
        'mum' => 'f2d758f9e379babc91f1f5062e2d486a70008cccc3c5d47b75f645e588a0ea09',
        'dad' => '6fe8ecbc1deafa51c2ecf088cf364eba1ceba9032ffbe2621e771b90ea93153d'
    ),
    'editors' => array(
        'john' => '96d9632f363564cc3032521409cf22a852f2032eec099ed5967c0d000cec607a',
        'marc' => '4697c20f8a70fcad6323e007d553cfe05d4433f81be70884ea3b4834b147f4c1',
        'admins' => array(
            'bill' => '623210167553939c87ed8c5f2bfe0b3e0684e12c3a3dd2513613c4e67263b5a1'
        )
    )
);
$config['rights'] = array(
    'family-things' => 'family',
    'secret/infos' => 'editors',
    'secret/infos/' => 'editors/admins',
    'just-for-john' => 'editors/john'
);
// $config['hash_type'] = 'sha256'; // by default, see php hash_algos
```

### Users and groups

The setting "*users*" is an array of all the users and their hashed passwords.

> There is numerous command line or online tools to hash a string, depending on the algorithm. PicoUsers uses `sha256` by default, and you can change it using a `hash_type` config setting.

You can create groups of users by using sub-arrays, and nest groups to create hierarchical systems.

    john => 2cc13a9e718d3d3051ac1f0ba024a2ff77485f4b
    editors
        marc => 9d4e1e23bd5b727046a9e3b4b7db57bd8d6ee684
        admins
            bill => 3cbcd90adc4b192a87a625850b7f231caddf0eb3

Users are defined by their user path. In the previous example, we have three users : `john` and `editors/marc` and `editors/admins/bill`. Bill will inherit the rights of its two groups, when john don't have the rights of any group.

### Rights

The setting "*rights*" is a flat list of rules, associating an URL to a user or a group of users to whom this path is reserved.

You can target a specific page or all pages in a directory by using or not a trailing slash.

## Login and logout

You can include a basic login/logout form with the Twig variable :

    {{ login_form }}

Making a custom login form would simply require sending by *POST* a login and password or a logout order. The following variables may be used :

Twig | Example
---|---
`{{ user }}`|editors/john
`{{ username }}`|john
`{{ usergroup }}`|editors

For example :
```html
<form method="post" action="">
{% if user %}
    Logged as {{ username }} ({{ usergroup }})
    (<input type="submit" name="logout" value="logout" />)
{% else %}
    <input type="text" name="login" />
    <input type="password" name="password" />
    <input type="submit" value="login" />
{% endif %}
</form>
```

You may want a small login/logout in the site headers for example and a fancy login form in the [error page](#error-page).

## Error page

When a visitor try to access to a restricted page, a *403 forbidden* header is sent, and the file `content/403.md` is shown. If there is no 403 page, the 404 one will be shown instead (and there will be no obvious clue that the requested page exists).
