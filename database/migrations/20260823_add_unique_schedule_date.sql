-- Match the application rule that only one clinic schedule may exist on a date.
ALTER TABLE schedules
  ADD UNIQUE KEY uq_schedules_sched_date (sched_date);
