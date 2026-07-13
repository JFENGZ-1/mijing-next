function() {
  var page = getCurrentPages().pop();
  var s = page.$vm ? page.$vm.setupState : null;
  if (!s) {
    return { noSetup: true, data: page.data };
  }
  return {
    checking: s.checking.value,
    loading: s.loading.value,
    forbidden: s.forbidden.value,
    error: s.errorMessage.value,
    sessionLen: s.sessions.value.length,
    canView: s.canViewBoard.value,
    selectedDate: s.selectedDate.value,
  };
}
