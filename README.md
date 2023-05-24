
# Translation Package

This package allows you to manage models and his translations in database.


## Installation
1. This package can be used with Laravel 8.0 or higher.
 you can install the package via composer:

```php
composer require mosab/translation
```

2. Optional: The service provider will automatically get registered. Or you may manually add the service provider in your config/app.php file:

```php
'providers' => [
    // ...
    Mosab\Translation\TranslationServiceProvider::class,
];
```

3. You should publish the migrations and seeder files:

```php
php artisan vendor:publish --provider="Mosab\Translation\TranslationServiceProvider"
```

- if you want to publish only migrations files:
```php
php artisan vendor:publish --provider="Mosab\Translation\TranslationServiceProvider" --tag=migrations
```

- if you want to publish only seeders files:
```php
php artisan vendor:publish --provider="Mosab\Translation\TranslationServiceProvider" --tag=seeders
```
4. Run the migrations:
  After the migration have been published, you can create the tables for this package by running:
```php
php artisan migrate
``` 
5. Run the seeder

```php
php artisan db:seeder --class=TranslationsLanguageSeeder 
```

Automatically after executing seeding, your project will support Arabic and English languages.
But, if you want to add a new language, you have to add this language in the following way:
- add code for new language in TranslationsLanguageSeeder at $all_languages variable, like this:
```php
RequestLanguage::$all_languages = ['en', 'ar', 'code for new language'];
```
- then you can add new language
```php
TranslationsLanguage::create([
    'code'  => 'code for new language',
    'title' => 'new language name',
]);
```
<span style="color:green"><b>Note:</b></span>.
you can manage Languages or modify it by dealing with translations_languages table directly.

6. list the middleware class in the $middleware property of your app/Http/Kernel.php class:

```php
protected $middlewareGroups = [
        'api' => [
            \Mosab\Translation\Middleware\RequestLanguage::class,
            // ...
        ],
    ];
```

## Usage Instructions
### Set  Up Translatable Model Class
1. After determine the tables that you want to translate some column of them, modify the model for this table by inheriting TranslatableModel, like this:

```php
use Mosab\Translation\Database\TranslatableModel;

class Test extends TranslatableModel
{
    // ...
}
```

2. Add new protected variable called translatable, and the type of this variable must be array.
You should add the columns you want to translate from this model in the translatable variable.
 for example:

```php
class Employee extends TranslatableModel
{
    // ...
    protected $fillable = [
        'salary'
    ];

    protected $translatable = [
        'name',
        'position',
    ];
}
```
<span style="color:green"><b>Note:</b></span>.
you should not add the columns you want to translate in migration files.
just add the another columns that have no translation.
for example:

```php
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->float('salary');
            $table->timestamps();
        });
    }
     /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
}
```
As the translatable columns are automatically stored at the translations table in database.

### Inserting Translatable Models
When you need to insert new records, you should instantiate a new model instance and set attributes on the model. Then, call the save method on the model instance.
Note that When you adding translation for a specific column, you must assign the value as:
- string value, then all translation values take the same value.
- or as array key => value.
for example:
```php
class EmployeeController extends Controller
{
    // ...
    public function store(Request $request)
    {
        // Validate the request...
 
        $employee = new Employee();
 
        $employee->name = [
            'en'  => 'name in english',
            'ar'  => 'name in arabic',
        ];
        $employee->position = [
            'en'  => 'position in english',
            'ar'  => 'position in arabic',
        ];
        $employee->salary = 5000,
 
        $employee->save();
    }
}
```
You may use the create method to "save" a new model, like this:

```php
class EmployeeController extends Controller
{
    // ...
    public function store(Request $request)
    {
        // Validate the request...
 
        $employee = Employee::create([
            'name'     => 'translation name',
            'position' => 'translation position',
            'salary'   => 5000,
        ]);
    }
}
```
If you want to add validate for request you can use function called translation_rule(), this method forces the user to enter the value with its translations in all languages ​​supported in the system.
for example:

```php
class EmployeeController extends Controller
{
    // ...
    public function store(Request $request)
    {
        $request->validate([
            'name'      => ['required', 'array', translation_rule()],
            'position'  => ['required', 'array', translation_rule()],
            'salary'    => ['required']
        ]); 

        $employee = new Employee();
 
        $employee->name = $request->name,
        $employee->position = $request->position,
        $employee->salary = $request->salary,
 
        $employee->save();
    }
}
```
### Updating Translatable Models
The save method may also be used to update models that already exist in the database. To update a model, you should retrieve it and set any attributes you wish to update. Then, you should call the model's save method.
When you update any translatable column, the old translation value delete from translations table and insert the new translation value in this table.
for example:
```php
class EmployeeController extends Controller
{
    // ...
    public function update($id ,Request $request)
    {
        // Validate the request...

       $employee = Employee::find($id);

       $employee->name = [
            'en'  => 'name in english',
            'ar'  => 'name in arabic',
        ];
        $employee->position = [
            'en'  => 'position in english',
            'ar'  => 'position in arabic',
        ];
 
        $employee->save();
    }
}
```
You may use the update method to "update" a model, like this:
```php
class EmployeeController extends Controller
{
    // ...
    public function update($id ,Request $request)
    {
        // Validate the request...

       $employee = Employee::find($id);

       $employee->update = [
            'name'     => ['en'=> 'name in english','ar'=> 'name in arabic'], 
            'position' => ['en'=> 'position in english','ar'=> 'position in arabic'],
       ];
    }
}
```
### Deleting Translatable Models
To delete a model, you may call the delete method on the model instance:
When you delete a model, his translations are automatically deleted from the translation table.
```php
class EmployeeController extends Controller
{
    // ...
    public function destroy($id)
    {
       $employee = Employee::find($id);

       $employee->delete();
    }
}
```
### Retrieving Translatable Models
When you need to retrieve a model, you can do it by this way:
```php
class EmployeeController extends Controller
{
    // ...
    public function show($id)
    {
        $employee = Employee::find($id);
        
        return $employee;
    }
}
```
Then the model and his translation returned in this way:
```
{
	"id": 1,
	"name": "name in english",
	"position": "position in english",
	"salary": 5000,
	"translations": {
		"name": {
			"ar": "name in arabic",
			"en": "name in english"
		},
		"position": {
			"ar": "position in english",
			"en": "position in arabic"
		}
	}
}
```
In this package you can specify which language you want to return the  translatable columns in, by sending the language code you want in the headers, like this:
```php
Accept-Language : en;
```
By default if you don't send the accept-language header it will take English as the default language.
All This is provided by RequestLanguage middleware.
