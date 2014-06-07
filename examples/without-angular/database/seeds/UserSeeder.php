<?php

class UserSeeder extends DatabaseSeeder
{
    /**
     * Run seeds
     *
     * @return void
     */
    public function run()
    {
        $this->seedUser();
    }

    /**
     * Add products to the database
     *
     * @return void
     */
    private function seedUser()
    {
        User::truncate();
        UserAddress::truncate();

        Input::replace(array(
            'username'  => 'Test', 
            'email'     => 'test@test.com', 
            'addresses' => array(
                array(
                    'address' => '123 Fake Street',
                    'city'    => 'Faketon'
                ),
                array(
                    'address' => '10 Test Street',
                    'city'    => 'Testville'
                )
            )
        ));

        $user = new User;
        $user->saveRecursive();
    }
}
