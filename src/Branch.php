<?php
namespace HgView;

use VcsCommon\BaseBranch;

/**
 * Represents Mercurial branch model
 */
class Branch extends BaseBranch
{
    /**
     * Returns head commit instance
     *
     * @return Commit
     * @throws CommonException
     */
    public function getHeadCommit()
    {
        return $this->repository->getCommit($this->head);
    }
}
