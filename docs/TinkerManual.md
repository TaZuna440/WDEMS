Laravel Tinker User Manual

php artisan tinker

use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'name' => 'John',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
]);

User::all();


1. Purpose

This manual explains how to use Laravel Tinker to manage and test user accounts in the WDEMS Laravel application.

Tinker allows an authorized developer or system administrator to perform tasks directly from the command line, such as:

Creating a user account
Viewing user accounts
Finding a specific user
Changing a user's password
Updating user information
Deleting a user account

This manual assumes that Laravel and the WDEMS application are already installed.

2. Requirements

Before using Tinker, make sure:

WDEMS is installed on the computer.
PHP is installed.
Laravel dependencies have been installed.
The WDEMS database is configured.
You have access to the WDEMS project directory.
You have permission to modify user accounts.
3. Open the WDEMS Project

Open a terminal and navigate to the WDEMS project.

Example:

cd ~/WDEMS


You should be inside the directory containing the Laravel application.

You can verify this by running:

ls


You should see directories such as:

app
database
routes
resources
vendor

4. Start Laravel Tinker

Run:

php artisan tinker


If Tinker starts successfully, you will see a prompt similar to:

Psy Shell v0.12.x
>


The > symbol means Tinker is ready for commands.

5. Create a User Account

To create a user, first load the User model:

use App\Models\User;


Then load Laravel's password hashing service:

use Illuminate\Support\Facades\Hash;


Create the account:

User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
]);


If successful, Laravel will display information about the newly created user.

The account has now been stored in the WDEMS database.

Example

If you want to create an account for Jane:

User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'password' => Hash::make('mypassword123'),
]);

6. Important: Use a Unique Email

Each user's email address must be unique.

For example, if this account already exists:

Admin
admin@example.com


you cannot create another account using:

admin@example.com


Laravel will display an error similar to:

Duplicate entry 'admin@example.com'


Instead, use another email:

User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
]);

7. View All User Accounts

To display all users:

User::all();


This allows you to check whether an account was successfully created.

You may see:

[
    App\Models\User {
        id: 1,
        name: "Admin",
        email: "admin@example.com",
    },
    App\Models\User {
        id: 2,
        name: "John Doe",
        email: "john@example.com",
    }
]

8. Find a User by ID

To find a specific user by their ID:

User::find(1);


For example:

User::find(2);


will retrieve the user whose ID is 2.

9. Find a User by Email

You can search for a user using their email address:

User::where('email', 'john@example.com')->first();


This is useful when you know the user's email but don't know their user ID.

10. Check Whether an Account Exists

To check whether an email is already registered:

User::where('email', 'john@example.com')->exists();


If the account exists, Tinker returns:

true


If it doesn't exist:

false


This is useful before creating a new account.

11. Change a User's Name

First find the user:

$user = User::where('email', 'john@example.com')->first();


Change the name:

$user->name = 'John Smith';


Save the change:

$user->save();


The user's name is now updated.

12. Change a User's Password

To change a user's password, find the account:

$user = User::where('email', 'john@example.com')->first();


Set the new password:

$user->password = Hash::make('newpassword123');


Save the change:

$user->save();


The user can now log in using the new password.

Always use Hash::make() when setting a password.

Do not do this:

$user->password = 'newpassword123';

13. Delete a User

Deleting an account permanently removes the user from the database.

First find the user:

$user = User::where('email', 'john@example.com')->first();


Then delete the account:

$user->delete();


Verify that the account is gone:

User::where('email', 'john@example.com')->exists();


The result should be:

false


Use caution when deleting accounts.

14. Create an Administrator Account

If the WDEMS application uses the users table for administrator authentication, an administrator can be created through Tinker.

Start Tinker:

php artisan tinker


Load the required classes:

use App\Models\User;
use Illuminate\Support\Facades\Hash;


Create the administrator:

User::create([
    'name' => 'Admin',
    'email' => 'admin2@example.com',
    'password' => Hash::make('your-secure-password'),
]);


Use an email address that does not already exist.

15. Verify the Account in MySQL

You can verify that the account was created by checking the database.

Exit Tinker:

exit


Open MySQL:

mysql -u root -p


Select the WDEMS database:

USE wdems;


View the users:

SELECT id, name, email, created_at FROM users;


You should see the newly created account.

16. Tinker Does Not Require a Registration Page

A registration page is not required to create an account.

For example, Tinker can directly perform:

Tinker
   ↓
User::create()
   ↓
users table
   ↓
Account created


A registration page would simply provide another way for a user to trigger the same type of database operation.

Therefore, if WDEMS does not currently have a /register page, an authorized administrator can still create accounts through Tinker.

17. Laravel Seeder vs. Tinker

WDEMS also contains a database seeder.

A seeder is normally used for creating predefined or test data.

For example:

php artisan db:seed


Tinker is different because it allows you to manually perform an operation whenever you need it.

Use Tinker when:
You need to create one account manually.
You need to reset a password.
You need to inspect an account.
You need to test something during development.
You need to make a quick database change.
Use a Seeder when:
You need predefined accounts.
You need repeatable test data.
You are setting up a new development database.
18. Complete Example: Creating an Account

The following is a complete example from beginning to end.

Step 1 — Open the project
cd ~/WDEMS

Step 2 — Start Tinker
php artisan tinker

Step 3 — Load the User model
use App\Models\User;

Step 4 — Load password hashing
use Illuminate\Support\Facades\Hash;

Step 5 — Create the account
User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
]);

Step 6 — Verify the account
User::where('email', 'john@example.com')->first();

Step 7 — Exit Tinker
exit


The account is now stored in the WDEMS database.

19. Common Problems
"Duplicate entry"

Example:

Duplicate entry 'admin@example.com'

Cause

An account with that email already exists.

Solution

Use a different email or update the existing account.

Check:

User::where('email', 'admin@example.com')->first();

"Class User not found"

If you try:

User::all();


before importing the model, Tinker may not know which User class you mean.

Run:

use App\Models\User;


Then try again.

Password Does Not Work

Make sure the password was created using:

Hash::make('your-password')


For example:

User::create([
    'name' => 'John',
    'email' => 'john@example.com',
    'password' => Hash::make('password123'),
]);

20. Quick Reference
Task	Command
Start Tinker	php artisan tinker
Exit Tinker	exit
Load User model	use App\Models\User;
Load Hash	use Illuminate\Support\Facades\Hash;
View users	User::all();
Find by ID	User::find(1);
Find by email	User::where('email', '...')->first();
Check account	User::where('email', '...')->exists();
Create account	User::create([...]);
Save changes	$user->save();
Delete account	$user->delete();
21. Recommended Procedure for WDEMS

When an administrator needs to manually create a WDEMS account:

Open the terminal.
Navigate to the WDEMS directory.
Start Tinker using php artisan tinker.
Load App\Models\User.
Load Laravel's Hash service.
Create the account using User::create().
Make sure the email address is unique.
Use Hash::make() for the password.
Verify the account using User::where(...).
Exit Tinker.

This procedure allows administrators to create accounts even when WDEMS does not provide a registration page.

22. Security Reminder

Tinker provides direct access to the application's data and functionality.

Only authorized personnel should use it.

In particular:

Do not share administrator passwords.
Do not store plain-text passwords in the database.
Do not delete accounts unless authorized.
Check the database before making destructive changes.
Avoid running untested commands against a production database.
Use strong passwords for administrator accounts.