//go:build windows

package main

func newEnumerator() Enumerator { return WindowsEnumerator{} }
func newExecutor() Executor     { return SumatraExecutor{} }
