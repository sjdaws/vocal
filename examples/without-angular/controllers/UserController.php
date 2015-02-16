<?php

class UserController extends BaseController
{
    /**
     * Get the first user, with their addresses
     *
     * @return View
     */
    public function get()
    {
        return View::make('addressbook')->with('user', User::with('addresses')->find(1));
    }

    /**
     * Save a user
     *
     * @return Redirect|View
     */
    public function save()
    {
        $user = (Input::get('id')) ? User::find(Input::get('id')) : new User;
        $result = $user->saveRecursive();

        if ( ! $result) return Redirect::back()->withInput()->with('errors', $user->getErrors());

        return View::make('addressbook')->with('user', $user)->with('success', true);
    }
}
