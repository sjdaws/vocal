var app = angular.module('app', ['ngRoute']);

app.config(function($routeProvider, $locationProvider)
{
    $routeProvider.

    when('/', {
        controller:  'addressBookController',
        resolve:        {
            data: function(addressBookModel)
            {
                return addressBookModel.get();
            }
        },
        templateUrl: 'addressbook.html'
    })

    .otherwise({
        redirectTo: '/'
    });

    $locationProvider.html5Mode(true);
});

app.controller('addressBookController', function($http, $scope, data)
{
    $scope.user = data;

    // Add a new address
    $scope.add = function()
    {
        $scope.user.addresses.push({});

        return false;
    }

    // Recursively process errors
    $scope.handleErrors = function(errors)
    {
        if ( ! errors) return;

        var html = '<ul>';

        angular.forEach(errors, function(error, field)
        {
            if (angular.isObject(error))
            {
                html += '<li>' + field;
                html += $scope.handleErrors(error) + '</li>';
            }
            else html += '<li>' + (($.isArray(error)) ? error.join() : error) + '</li>';
        });

        return html + '</ul>';
    }

    // Save entire address book
    $scope.save = function()
    {
        $('#info').css('background', '#d9edf7').html('Saving...');

        $http({method: 'POST', url: '/', data: $scope.user}).then(function(response)
        {
            if (response.data.errors)
            {
                var errors = '<div>Save unsuccessful!<div>';

                var list = $scope.handleErrors(response.data.errors);

                $('#info').css('background', '#f2dede').html(errors + list);
            }
            else
            {
                $scope.user = response.data;
                $('#info').css('background', '#dff0d8').html('Save success!');
            }
        });

        return false;
    }
});

app.factory('addressBookModel', function($http)
{
    return {

        get: function()
        {
            var promise = $http({method: 'GET', url: '/json'}).then(function(response)
            {
                return response.data;
            });

            return promise;
        }

    }
});
