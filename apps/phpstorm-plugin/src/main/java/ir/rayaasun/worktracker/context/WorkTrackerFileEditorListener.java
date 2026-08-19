package ir.rayaasun.worktracker.context;

import com.intellij.openapi.fileEditor.FileEditorManagerEvent;
import com.intellij.openapi.fileEditor.FileEditorManagerListener;
import com.intellij.openapi.project.Project;
import org.jetbrains.annotations.NotNull;

public final class WorkTrackerFileEditorListener implements FileEditorManagerListener {
    private final Project project;

    public WorkTrackerFileEditorListener(@NotNull Project project) {
        this.project = project;
    }

    @Override
    public void selectionChanged(@NotNull FileEditorManagerEvent event) {
        project.getService(WorkTrackerContextPublisherService.class).publishSoon();
    }
}
