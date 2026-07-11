import { StatusBar } from 'expo-status-bar';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { StyleSheet, View } from 'react-native';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { InvitationsBadgeProvider } from './contexts/InvitationsBadgeContext';
import { MessagesBadgeProvider } from './contexts/MessagesBadgeContext';
import LoginScreen from './screens/LoginScreen';
import RegisterScreen from './screens/RegisterScreen';
import JournalScreen from './screens/JournalScreen';
import NewEntryScreen from './screens/NewEntryScreen';
import LiaisonsScreen from './screens/LiaisonsScreen';
import InvitationsScreen from './screens/InvitationsScreen';
import MessagesScreen from './screens/MessagesScreen';
import ConversationScreen from './screens/ConversationScreen';
import AppointmentScreen from './screens/AppointmentScreen';
import NewAppointmentScreen from './screens/NewAppointmentScreen';
import ExportScreen from './screens/ExportScreen';
import AccountScreen from './screens/AccountScreen';
import SessionExpiryWarning from './components/SessionExpiryWarning';

const Stack = createStackNavigator();

function RootNavigator() {
  const { isAuthenticated } = useAuth();

  return (
    <NavigationContainer>
      <Stack.Navigator
        screenOptions={{ headerShown: false }}
        initialRouteName={isAuthenticated ? 'Journal' : 'Login'}
      >
        {isAuthenticated ? (
          <>
            <Stack.Screen name="Journal" component={JournalScreen} />
            <Stack.Screen name="NewEntry" component={NewEntryScreen} />
            <Stack.Screen name="Liaisons" component={LiaisonsScreen} />
            <Stack.Screen name="Invitations" component={InvitationsScreen} />
            <Stack.Screen name="Messages" component={MessagesScreen} />
            <Stack.Screen name="Conversation" component={ConversationScreen} />
            <Stack.Screen name="Appointments" component={AppointmentScreen} />
            <Stack.Screen name="NewAppointment" component={NewAppointmentScreen} />
            <Stack.Screen name="Export" component={ExportScreen} />
            <Stack.Screen name="Account" component={AccountScreen} />
          </>
        ) : (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="Register" component={RegisterScreen} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

// There's no global DOM to listen for mouse/keyboard activity on native, so
// the inactivity timer (see AuthContext) is reset from here instead: this
// wraps the whole app and observes every touch via the raw touch events
// (not the legacy Responder System, which would fight react-navigation's
// own gesture handling) without stealing it from whichever child (button,
// input...) it started on.
function ActivityCapture({ children }) {
  const { registerActivity } = useAuth();

  return (
    <View style={styles.flexFill} onTouchStart={registerActivity}>
      {children}
    </View>
  );
}

export default function App() {
  return (
    <GestureHandlerRootView style={styles.flexFill}>
      <AuthProvider>
        <InvitationsBadgeProvider>
          <MessagesBadgeProvider>
            <ActivityCapture>
              <RootNavigator />
              <SessionExpiryWarning />
              <StatusBar style="auto" />
            </ActivityCapture>
          </MessagesBadgeProvider>
        </InvitationsBadgeProvider>
      </AuthProvider>
    </GestureHandlerRootView>
  );
}

const styles = StyleSheet.create({
  flexFill: { flex: 1 },
});
