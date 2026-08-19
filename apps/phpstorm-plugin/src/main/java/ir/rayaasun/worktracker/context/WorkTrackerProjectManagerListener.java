package ir.rayaasun.worktracker.context;

import com.intellij.openapi.project.Project;
import com.intellij.openapi.project.ProjectManagerListener;
import org.jetbrains.annotations.NotNull;

public final class WorkTrackerProjectManagerListener implements ProjectManagerListener {
    @Override
    public void projectOpened(@NotNull Project project) {
        project.getService(WorkTrackerContextPublisherService.class).start();
    }
}
