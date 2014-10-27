<?php

class UserController extends BaseController
{
    /**
     * Get user data in JSON
     *
     * @return string
     */
    public function get()
    {
        return User::with('addresses')->find(1);
    }

    /**
     * Return template for Angular
     *
     * @return View
     */
    public function index()
    {
        // Change blade tags so they don't clash with Angular
        Blade::setEscapedContentTags('[[[', ']]]');
        Blade::setContentTags('[[', ']]');

        return View::make('addressbook');
    }

    /**
     * Save a user
     *
     * @return Redirect|View
     */
    public function save()
    {
        // Find record if we have an ID, otherwise create a new record
        $user = (Input::get('id')) ? User::with('addresses')->find(Input::get('id')) : new User;
        $result = $user->saveRecursive();

        // Capture errors to return via JSON model if we have some
        if ( ! $result) $user->errors = $user->errors();

        return $user;
    }
}
