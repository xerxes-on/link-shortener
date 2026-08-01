<?php

namespace App\Policies;

use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_notification_rule');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NotificationType $notificationType): bool
    {
        return $user->can('view_notification_rule');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_notification_rule');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NotificationType $notificationType): bool
    {
        return $user->can('update_notification_rule');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NotificationType $notificationType): bool
    {
        return $user->can('delete_notification_rule');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_notification_rule');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, NotificationType $notificationType): bool
    {
        return $user->can('force_delete_notification_rule');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_notification_rule');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, NotificationType $notificationType): bool
    {
        return $user->can('restore_notification_rule');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_notification_rule');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, NotificationType $notificationType): bool
    {
        return $user->can('replicate_notification_rule');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_notification_rule');
    }
}
