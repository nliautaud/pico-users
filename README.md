# Pico Users

A hierarchical users and rights system plugin for [Pico CMS](http://pico.dev7studios.com).

Login and logout system, unlimited users and hierarchical user groups, simple rights management.

* [Installation](#installation)
* [Users management](#users-management)
* [Rights management](#rights-management)
* [Login and logout](#login-and-logout)
* [Error page](#error-page)


### Installation

Copy `_pico_users.php` to the `plugins/` directory of your Pico Project.

### Users management

Create a new setting "*users*" in your Pico `config.php` file. This setting is a list of all the users and their passwords, stored as sha1 strings :

	username => 2cc13a9e718d3d3051ac1f0ba024a2ff77485f4b
	otheruser => 12dea96fec20593566ab75692c9949596833adc9

To convert a password, you may use an online tool like [sha1.cz](http://sha1.cz) or [sha1.fr](http://sha1.fr)

You can create groups of users by using sub-arrays. Users are defined by their *user path* You will be able to give rights to specific groups.

	group1
		john => 2cc13a9e718d3d3051ac1f0ba024a2ff77485f4b
		marc => 12dea96fec20593566ab75692c9949596833adc9
	group2
		bill => 3cbcd90adc4b192a87a625850b7f231caddf0eb3

And above all, you can nest groups to create hierarchical systems :

	john => 2cc13a9e718d3d3051ac1f0ba024a2ff77485f4b
	foo
		marc => 9d4e1e23bd5b727046a9e3b4b7db57bd8d6ee684
		bar
			bill => 3cbcd90adc4b192a87a625850b7f231caddf0eb3

In the previous example, bill is defined by `foo/bar/bill` and have the rights of the two groups, when john is just defined by `john` ant don't have the rights of any group.

Here is an example of a typical hierarchy :

```php
$settings['users'] = array
(
	'family' => array(
		'mum' => '2cc13a9e718d3d3051ac1f0ba024a2ff77485f4b',
		'dad' => '12dea96fec20593566ab75692c9949596833adc9'
	)
	'editors' => array(
		'john' => '9d4e1e23bd5b727046a9e3b4b7db57bd8d6ee684',
		'marc' => '12dea96fec20593566ab75692c9949596833adc9'
		'admins' => array(
			'bill' => '3cbcd90adc4b192a87a625850b7f231caddf0eb3'
		)
	)
);
```

### Rights management

Create a new setting "*rights*" in your Pico `config.php` file.

This setting is a flat list of rules associating a path to a user or a group of users to whom this path is reserved. You can target a specific page or all pages in a directory by using or not a trailing slash.

	specific/page => this/user
	all/files/in/here/ => this/usergroup

Here is an example of such a setting :

```php
$settings['rights'] = array
(
	'family-things' => 'family',
	'secret/infos' => 'editors',
	'secret/infos/' => 'editors/admins',
	'just-for-john' => 'editors/john'
);
```

### Login and logout

**TL;DR** : You can include a basic login/logout form anywhere with the Twig variable `{{ login_form }}`.

A login form, in pages or themes, would send by *POST* a login and password :

```html
<form method="post" action="">
	<input type="text" name="login" />
	<input type="password" name="password" />
	<input type="submit" value="login" />
</form>
```

A logout form would send by *POST* a logout order :

```html
<form method="post" action="">
	<input type="submit" name="logout" value="logout" />
</form>
```

The Twig variable `{{ user }}` contains the logged user path. If empty, the user is not logged. Thus, a more complex form would adapt to the login state :

```html
<form method="post" action="">
	{% if user %}
	Logged as {{ user }}
	(<input type="submit" name="logout" value="logout" />)
	{% else %}
	<input type="text" name="login" />
	<input type="password" name="password" />
	<input type="submit" value="login" />
	{% endif %}
</form>
```

For example, a small login/logout form might be included in the site headers, when a big login form might be included in the [error page](#error-page).

### Error page

When a visitor try to access to a restricted page, a *403 forbidden* header is sent, and the file `content/403.md` is shown. If there is no 403 page, the 404 one will be shown instead (and there will be no obvious clue that the requested page exists).