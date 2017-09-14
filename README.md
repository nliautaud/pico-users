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

Copy the `PicoUsers` directory to the `plugins/` directory of your Pico Project.

## Settings

Users, rights and others settings should be stored in Pico `config/config.php` file.

```php
$config['users'] = array(
    'john' => '$2a$08$kA7StQeZgyuEJnIrvypwEeuyjSqxrvavOBSf33n4yWSJFhbQAkO1W',
    'editors' => array(
        'marc' => '$2a$08$V/En.8vnZFWGOwXvDvFYsO8PTq.KSA5eYTehICnErFnd3V.zzsj.K',
        'admins' => array(
            'john' => '$2a$08$bCVTtxqH/VxWuHqrZQ/QiOEcvvbVjl9UD3mTf.7AnXhS90DXj5IZ6'
        )
    ),
    'family' => array(
        'mum' => '$2a$08$qYtklDGOy/cCK1K0Zh8qROkFW3/V7gFgve.0GQv/sPmLYHm0jEiTi',
        'dad' => '$2a$08$Eu7aKmOLz1Jme4iReWp6r.TfI2K3V3DyeRDV8oBS6gMtDPessqqru'
    )
);
$config['rights'] = array(
    'family-things' => 'family',
    'secret/infos' => 'editors',
    'secret/infos/' => 'editors/admins',
    'just-for-john' => 'john'
);
```

### Users and groups

The setting `users` is an array of user names and `bcrypt` hashed passwords.

You can create groups of users by using sub-arrays, and nest groups to create hierarchical systems.

Users are defined by their user path. In the previous example, the users `john` and `editors/admins/john` are two distinct users. "Admin John" will inherit the rights of its two groups, when "just John" don't have the rights of any group.

### Rights

The setting `rights` is a flat list of rules, associating an URL to a user or a group of users to whom this path is reserved.

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
