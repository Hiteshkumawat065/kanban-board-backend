<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityAction: string
{
    case WorkspaceCreated = 'workspace.created';
    case MemberInvited = 'workspace.member.invited';
    case MemberJoined = 'workspace.member.joined';
    case MemberRemoved = 'workspace.member.removed';

    case BoardCreated = 'board.created';
    case BoardUpdated = 'board.updated';
    case BoardArchived = 'board.archived';

    case ListCreated = 'list.created';
    case ListRenamed = 'list.renamed';
    case ListArchived = 'list.archived';

    case CardCreated = 'card.created';
    case CardUpdated = 'card.updated';
    case CardMoved = 'card.moved';
    case CardArchived = 'card.archived';
    case CardCompleted = 'card.completed';

    case CommentAdded = 'comment.added';
    case AssigneeAdded = 'card.assignee.added';
    case AssigneeRemoved = 'card.assignee.removed';
    case LabelToggled = 'card.label.toggled';
    case AttachmentAdded = 'card.attachment.added';

    case CardUatApproved = 'card.uat.approved';
    case CardUatRework = 'card.uat.rework';
}
