<?php

namespace App\Http\Controllers\Panel\Boards;

use App\Board;
use App\User;
use App\Http\Controllers\Panel\PanelController;
use Input;
use Validator;
use Event;
use App\Events\UserRolesModified;

class StaffController extends PanelController
{
    /*
    |--------------------------------------------------------------------------
    | Staff Controller
    |--------------------------------------------------------------------------
    |
    | This is the board staff controller, available only to the board owner and admins.
    | Its only job is to list, remove, update, and add staff members to a board.
    |
    */

    const VIEW_LIST = 'panel.board.staff';
    const VIEW_ADD = 'panel.board.staff.create';
    const VIEW_EDIT = 'panel.board.staff.edit';

    /**
     * View path for the secondary (sidebar) navigation.
     *
     * @var string
     */
    public static $navSecondary = 'nav.panel.board';

    /**
     * View path for the tertiary (inner) navigation.
     *
     * @var string
     */
    public static $navTertiary = 'nav.panel.board.settings';

    /**
     * List all staff members to the user.
     *
     * @return Response
     */
    public function getIndex(Board $board)
    {
        if (!$board->canEditConfig($this->user)) {
            return abort(403);
        }

        $roles = $board->roles;
        $staff = $board->getStaff();

        return $this->view(static::VIEW_LIST, [
            'board' => $board,
            'roles' => $roles,
            'staff' => $staff,

            'tab' => 'staff',
        ]);
    }

    /**
     * Opens staff creation form.
     *
     * @return Response
     */
    public function getAdd(Board $board)
    {
        if (!$board->canEditConfig($this->user)) {
            return abort(403);
        }

        $roles = $this->user->getAssignableRoles($board);
        $staff = $board->getStaff();

        return $this->view(static::VIEW_ADD, [
            'board' => $board,
            'roles' => $roles,
            'staff' => $staff,

            'tab' => 'staff',
        ]);
    }

    /**
     * Adds new staff.
     *
     * @return Response
     */
    public function putAdd(Board $board)
    {
        if (!$board->canEditConfig($this->user)) {
            return abort(403);
        }

        $createUser = false;
        $roles = $this->user->getAssignableRoles($board);
        $rules = [];
        $existing = Input::get('staff-source') == 'existing';

        if ($existing) {
            $rules = [
                'existinguser' => [
                    'required',
                    'string',
                    'exists:users,username',
                ],
                'captcha' => [
                    'required',
                    'captcha',
                ],
            ];

            $input = Input::only('existinguser', 'captcha');
            $validator = Validator::make($input, $rules);
        } else {
            $createUser = true;
            $validator = $this->registrationValidator();
        }

        $castes = $roles->pluck('role_id');
        $casteRules = [
            'castes' => [
                'required',
                'array',
            ],
        ];
        $casteInput = Input::only('castes');
        $casteValidator = Validator::make($casteInput, $casteRules);

        $casteValidator->each('castes', [
            'in:'.$castes->implode(','),
        ]);


        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($validator->errors());
        } elseif ($casteValidator->fails()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($casteValidator->errors());
        } elseif ($createUser) {
            $user = $this->registrar->create(Input::all());
        } else {
            $user = User::whereUsername(Input::only('existinguser'))->firstOrFail();
        }


        $user->roles()->detach($roles->pluck('role_id')->toArray());
        $user->roles()->attach($casteInput['castes']);

        Event::fire(new UserRolesModified($user));

        return redirect("/cp/board/{$board->board_uri}/staff");
    }
}
